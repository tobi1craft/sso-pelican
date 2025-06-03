<?php

declare(strict_types=1);

namespace Tobi1craft\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\WrapperClock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\EdDSA;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Tobi1craft\Sso\Checker\SubjectChecker;
use Tobi1craft\Sso\Checker\UserChecker;

class SsoController
{

    /**
     * Attempt to login the user
     */
    public function handle($token): RedirectResponse
    {
        if (!$this->hasToken($token)) {
            return redirect()->back()->withErrors('Token does not exists or has expired');
        }

        try {
            $id = $this->getUserId($token);
            $user = User::findOrFail($id);
            Auth::loginUsingId($id);
            $this->invalidateToken($token);

            if ($user->isAdmin()) {
                return redirect()->intended('/admin');
            }
            return redirect()->intended('/');
        } catch (\Exception $error) {
            return redirect()->back()->withErrors('Something went wrong, please try again.');
        }
    }

    /**
     * @deprecated Previous authentication method, use JWS instead
     */
    public function requestToken(Request $request): Response
    {
        if (!env('SSO_SECRET')) {
            return response(['success' => false, 'message' => 'Please configure a SSO Secret'], 403);
        }

        if ($request->input('sso_secret') !== env('SSO_SECRET')) {
            return response(['success' => false, 'message' => 'Please provide valid credentials'], 403);
        }

        $user = User::findOrFail($request->input('user_id'));

        if ($user['2fa']) {
            return response(['success' => false, 'message' => 'Logging into accounts with 2 Factor Authentication enabled is not supported.'], 501);
        }

        return response(['success' => true, 'redirect' => route('sso-tobi1craft.login', $this->generateToken($request->input('user_id')))], 200);
    }

    /**
     * Request login via JWS
     */
    public function requestLogin(Request $request): Response
    {
        assert($request->isMethod('POST'));
        $token = $request->getContent();

        if (!$token) {
            return response(['success' => false, 'message' => 'No token provided'], 400);
        }

        $isValid = $this->validateJWS($token);
        if ($isValid === true) {
            return response(['success' => true, 'redirect' => route('sso-tobi1craft.login', $this->generateToken($request->input('user_id')))], 200);
        }

        return $isValid;
    }

    /**
     * Validate the JWS token
     * 
     * @param string $token Compact JWS (header.payload.signature)
     */
    protected function validateJWS(string $token): bool|Response
    {
        $jwkJson = file_get_contents('https://www.tobi1craft.de/pelican-token');
        if (!$jwkJson) {
            response(['success' => false, 'message' => 'Failed to fetch public key'], 501);
        }
        $jwk = JWK::createFromJson($jwkJson);


        $serializerManager = new JWSSerializerManager([
            new CompactSerializer(),
        ]);
        $jws = $serializerManager->unserialize($token);


        $headerCheckerManager = new HeaderCheckerManager([new AlgorithmChecker(['EdDSA'])], [new JWSTokenSupport()]);
        $headerCheckerManager->check($jws, 0);


        $clock = new WrapperClock(Carbon::now());


        $claimCheckerManager = new ClaimCheckerManager([
            new IssuerChecker(['https://www.tobi1craft.de']),
            new AudienceChecker('https://pelican.tobi1craft.de'),
            new IssuedAtChecker($clock),
            new ExpirationTimeChecker($clock),
            new SubjectChecker(),
            new UserChecker()
        ]);
        try {
            $claimCheckerManager->check(json_decode($jws->getPayload(), true), ['iss', 'aud', 'iat', 'exp', 'sub', 'user']);
        } catch (\Exception $e) {
            return response(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $jwsVerifier = new JWSVerifier(new AlgorithmManager([new EdDSA()]));
        $isVerified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);


        return $isVerified ? true : response(['success' => false, 'message' => 'Invalid JWS token'], 403);
    }

    /**
     * Generate a random access token and link the user_id
     */
    protected function generateToken($user_id): string
    {
        $token = Str::random(48);
        Cache::add($token, $user_id, 60); // Store the token for 60 seconds
        return $token;
    }

    protected function getUserId($token): string
    {
        return Cache::get($token);
    }

    protected function hasToken($token): bool
    {
        return Cache::has($token);
    }

    protected static function invalidateToken($token): void
    {
        Cache::forget($token);
    }
}
