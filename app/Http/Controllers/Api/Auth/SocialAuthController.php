<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthProviderRegistry;
use App\Services\Auth\SocialAuthService;
use App\Services\Auth\TwoFactorChallengeStore;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly AuthProviderRegistry $registry,
        private readonly SocialAuthService $service,
        private readonly TwoFactorChallengeStore $challenges,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Public endpoint used by the LoginPage to render the right buttons:
     * returns the list of enabled providers (no secrets) + the local/register
     * flags. Fronteend pairs this with useAuthProviders().
     */
    public function listProviders(): JsonResponse
    {
        $canonicalProvider = $this->registry->canonicalProvider();
        $canonicalConfig = $this->registry->canonicalConfig();
        $canonicalRegisterUrl = (string) ($canonicalConfig['register_url'] ?? '');

        return response()->json([
            'providers' => $this->registry->enabledProviders(),
            'local_enabled' => $this->settings->get('auth_local_enabled', 'true') === 'true',
            'local_registration_enabled' => $this->settings->get('auth_local_registration_enabled', 'true') === 'true',
            // Only surface the canonical register URL when a canonical IdP is
            // actually enabled — otherwise the "Create account" CTA would
            // point to an IdP that can't authenticate the user afterwards.
            'canonical_provider' => $canonicalProvider,
            'canonical_register_url' => ($canonicalProvider !== null && $canonicalRegisterUrl !== '') ? $canonicalRegisterUrl : null,
        ]);
    }

    /**
     * 302-redirect to the provider's authorize URL. Throttled to 20 req/min
     * per IP to prevent scripted enumeration (plan §S4).
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        return redirect()->away($this->service->redirectUrl($provider));
    }

    /**
     * Callback target registered with the provider. Handles the full flow
     * and either logs the user in, redirects to the 2FA challenge, or
     * returns to /login with an error slug the frontend i18n can localise.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $outcome = $this->service->handleCallback($provider, $request);
        } catch (\App\Exceptions\Auth\SocialAuthException $e) {
            return redirect('/login?error='.$e->errorKey());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OAuth callback failed', [
                'provider' => $provider,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return redirect('/login?error=auth.social.oauth_failed');
        }

        if ($outcome->requires2fa) {
            $challengeId = $this->challenges->put($outcome->user->id, [
                'type' => 'oauth',
                'provider' => $provider,
                'intended_url' => null,
            ]);

            return redirect('/2fa/challenge?id='.$challengeId);
        }

        Auth::login($outcome->user, true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // When 2FA enforcement is on for admins and the freshly-authenticated
        // admin hasn't set up 2FA yet, send them straight to /2fa/setup
        // instead of /dashboard. Otherwise they'd land on /dashboard, hit
        // /admin, get a 403 from Filament's canAccessPanel, bounce back to
        // /login, click "Login with Shop" again, get the OAuth consent
        // prompt AGAIN — a frustrating loop that clears only when they
        // finish 2FA setup. The setup page knows it's enforced via
        // `?enforced=1` and disables the back/skip affordances.
        $user = $outcome->user;
        $enforceAdmin2fa = $this->settings->get('auth_2fa_required_admins', 'false') === 'true';
        if ($enforceAdmin2fa && $user->is_admin && ! $user->hasTwoFactor()) {
            return redirect('/2fa/setup?enforced=1');
        }

        return redirect('/dashboard');
    }

    /**
     * Delete an identity row for the current user. Plan §S7 enforced inside
     * the service — returns 422 when the user would be locked out.
     */
    public function unlink(Request $request, string $provider): JsonResponse
    {
        $this->service->unlink($request->user(), $provider, $request);

        return response()->json(['success' => true]);
    }

    /**
     * List the current user's linked identities. Used by LinkedAccountsList
     * in SecurityPage to show which providers are hooked up + which buttons
     * to disable (S7 last-method protection).
     */
    public function listLinked(Request $request): JsonResponse
    {
        $user = $request->user();

        $identities = $user->oauthIdentities()
            ->select(['id', 'provider', 'provider_email', 'last_login_at', 'created_at'])
            ->get();

        $hasPassword = ! empty($user->password);

        return response()->json([
            'data' => $identities->map(fn ($i): array => [
                'provider' => $i->provider,
                'provider_email' => $i->provider_email,
                'last_login_at' => $i->last_login_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])->all(),
            'can_unlink_any' => $hasPassword || $identities->count() > 1,
        ]);
    }
}
