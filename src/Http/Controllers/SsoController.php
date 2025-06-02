<?php

namespace Tobi1craft\Sso\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SsoController
{

    /**
     * Attempt to login the user
     *
     * @return Redirect
     */
    public function handle($token)
    {
        if (!$this->hasToken($token)) {
            return redirect()->back()->withError('Token does not exists or has expired');
        }

        try {
            $id = $this->getToken($token);
            $user = User::findOrFail($id);
            Auth::loginUsingId($id);
            $this->invalidateToken($token);

            if ($user->isAdmin()) {
                return redirect()->intended('/admin');
            }
            return redirect()->intended('/');
        } catch (\Exception $error) {
            return redirect()->back()->withError('Something went wrong, please try again.');
        }
    }

    /**
     * Handle incoming webhook
     *
     * @return $token
     */
    public function webhook(Request $request)
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
     * Generate a random access token and store the user_id inside
     * Tokens are only valid for 60 seconds
     *
     * @return mixed
     */
    protected function generateToken($user_id)
    {
        $token = Str::random(48);
        Cache::add($token, $user_id, 60); // Store the token for 60 seconds
        return $token;
    }

    /**
     * Returns the value of the token
     *
     * @return mixed
     */
    protected function getToken($token)
    {
        return Cache::get($token);
    }

    /**
     * Returns true or false based on if the token exists
     *
     * @return bool
     */
    protected function hasToken($token): bool
    {
        return Cache::has($token);
    }

    /**
     * Invalidates the token so it can no longer be used
     *
     * @return void
     */
    protected static function invalidateToken($token)
    {
        Cache::forget($token);
    }
}
