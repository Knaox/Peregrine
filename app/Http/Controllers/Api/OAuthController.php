<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('auth-mode.oauth.client_id'),
            'redirect_uri' => url('/api/oauth/callback'),
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]);

        return redirect(config('auth-mode.oauth.authorize_url') . '?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->pull('oauth_state');

        if (!$storedState || $storedState !== $request->input('state')) {
            return redirect('/login?error=invalid_state');
        }

        if ($request->has('error')) {
            return redirect('/login?error=' . $request->input('error'));
        }

        try {
            // Exchange code for token
            $tokenResponse = Http::asForm()->post(config('auth-mode.oauth.token_url'), [
                'grant_type' => 'authorization_code',
                'client_id' => config('auth-mode.oauth.client_id'),
                'client_secret' => config('auth-mode.oauth.client_secret'),
                'redirect_uri' => url('/api/oauth/callback'),
                'code' => $request->input('code'),
            ]);

            if (!$tokenResponse->successful()) {
                return redirect('/login?error=token_exchange_failed');
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Fetch user profile
            $userResponse = Http::withToken($accessToken)
                ->get(config('auth-mode.oauth.user_url'));

            if (!$userResponse->successful()) {
                return redirect('/login?error=user_fetch_failed');
            }

            $oauthUser = $userResponse->json();
            $email = $oauthUser['email'];
            $name = $oauthUser['name'] ?? $email;

            // Find or create local user
            $user = User::where('email', $email)->first();

            if ($user) {
                // Update OAuth fields
                $user->update([
                    'name' => $name,
                    'oauth_provider' => 'shop',
                    'oauth_id' => (string) ($oauthUser['id'] ?? ''),
                ]);

                // Sync email to Pelican if changed and user has pelican_user_id
                if ($user->pelican_user_id && $user->getOriginal('email') !== $email) {
                    try {
                        $this->pelicanService->updateUser($user->pelican_user_id, [
                            'email' => $email,
                        ]);
                    } catch (\Throwable) {
                        // Log but don't fail login
                    }
                    $user->update(['email' => $email]);
                }
            } else {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'password' => null,
                    'oauth_provider' => 'shop',
                    'oauth_id' => (string) ($oauthUser['id'] ?? ''),
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect('/dashboard');
        } catch (\Throwable) {
            return redirect('/login?error=oauth_failed');
        }
    }
}
