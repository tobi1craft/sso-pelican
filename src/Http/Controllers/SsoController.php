<?php

declare(strict_types=1);

namespace Tobi1craft\Sso\Http\Controllers;

use App\Models\User;
use Carbon\WrapperClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Attempt to login the user with the provided token
     * 
     * @param string $token The SSO token
     * @return RedirectResponse
     */
    public function handle(string $token): RedirectResponse
    {
        $id = $this->pullUserId($token);
        if (!$id) {
            return redirect()->back()->withErrors('Token does not exist or has expired.');
        }

        try {
            $user = User::findOrFail($id);
            Auth::loginUsingId($id);
            // Regenerate session to prevent fixation
            request()->session()->regenerate();

            if ($user->isAdmin()) {
                return redirect()->intended('/admin');
            }
            return redirect()->intended('/');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('User not found during SSO login', ['user_id' => $id]);
            return redirect()->back()->withErrors('User not found.');
        } catch (\Exception $error) {
            Log::error('Unexpected error during SSO login', ['exception' => $error, 'user_id' => $id]);
            return redirect()->back()->withErrors('Something went wrong, please try again.');
        }
    }

    /**
     * Request login via JWS token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function requestLogin(Request $request): JsonResponse
    {
        // Retrieve JWS token from Authorization: Bearer header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'No JWS token provided in Authorization header.'], 400);
        }

        $validationResult = $this->validateJWS($token);
        if (is_array($validationResult)) {
            return response()->json([
                'redirect' => route('sso-tobi1craft.login', $this->generateToken($validationResult['user_id']))
            ], 200);
        }

        return $validationResult;
    }

    /**
     * Validate the JWS token
     *
     * @param string $token Compact JWS (header.payload.signature)
     * @return array|JsonResponse Returns array with user_id on success, JsonResponse on failure
     */
    protected function validateJWS(string $token): array|JsonResponse
    {
        try {
            // Fetch public key, cached for 1 hour
            $jwkJson = Cache::get('sso_jwk_json');
            if (!$jwkJson) {
                $response = Http::timeout(10)->get(config('sso.public_key_endpoint'));
                if ($response->failed()) {
                    return response()->json(['message' => 'Failed to fetch public key'], 501);
                }
                $jwkJson = $response->body();
                Cache::put('sso_jwk_json', $jwkJson, now()->addSeconds(config('app.debug') ? 60 : 3600));
            }
            $jwk = JWK::createFromJson($jwkJson);

            $serializerManager = new JWSSerializerManager([
                new CompactSerializer(),
            ]);
            $jws = $serializerManager->unserialize($token);

            $headerCheckerManager = new HeaderCheckerManager([new AlgorithmChecker(['EdDSA'])], [new JWSTokenSupport()]);
            $headerCheckerManager->check($jws, 0);

            // First verify the signature before trusting claims
            $jwsVerifier = new JWSVerifier(new AlgorithmManager([new EdDSA()]));
            if (!$jwsVerifier->verifyWithKey($jws, $jwk, 0)) {
                $tokenHash = hash('sha256', $token);
                Log::warning('JWS verification failed', ['token_hash' => $tokenHash]);
                return response()->json(['message' => 'Invalid token'], 403);
            }

            $clock = new WrapperClock(Carbon::now());

            $claimCheckerManager = new ClaimCheckerManager([
                new IssuerChecker([config('sso.issuer')]),
                new AudienceChecker(config('sso.audience')),
                new IssuedAtChecker($clock),
                new ExpirationTimeChecker($clock),
                new SubjectChecker(),
                new UserChecker()
            ]);

            $payload = json_decode($jws->getPayload(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid token payload JSON', ['error' => json_last_error_msg()]);
                return response()->json(['message' => 'Invalid token payload'], 403);
            }

            $payload = $claimCheckerManager->check($payload, ['iss', 'aud', 'iat', 'exp', 'sub', 'user']);

            // At this point the token is verified and claims are validated
            return ['user_id' => $payload['user']];

        } catch (\Jose\Component\Checker\InvalidClaimException $e) {
            $tokenHash = hash('sha256', $token);
            Log::warning('Invalid claim in JWS', ['message' => $e->getMessage(), 'token_hash' => $tokenHash]);
            return response()->json(['message' => 'Invalid token. Claim "' . $e->getClaim() . '" is invalid.'], 403);
        } catch (\Exception $e) {
            Log::error('JWS validation error', ['exception' => $e]);
            return response()->json(['message' => 'Token validation failed'], 403);
        }
    }

    /**
     * Generate a random access token and link it to the user_id
     * 
     * @param int $user_id The user ID to associate with the token
     * @return string The generated token
     */
    protected function generateToken(int $user_id): string
    {
        $token = Str::random(48);
        Cache::put($token, $user_id, 60); // Store the token for 60 seconds
        return $token;
    }

    /**
     * Retrieve and remove the user ID associated with a token
     * 
     * @param string $token The token to lookup
     * @return int|null The user ID if found, null otherwise
     */
    protected function pullUserId(string $token): ?int
    {
        $userId = Cache::pull($token);
        return is_numeric($userId) ? (int) $userId : null;
    }
}
