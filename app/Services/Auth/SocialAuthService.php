<?php

namespace App\Services\Auth;

use App\Events\OAuthProviderLinked;
use App\Events\OAuthProviderUnlinked;
use App\Exceptions\Auth\LastLoginMethodException;
use App\Exceptions\Auth\ProviderDisabledException;
use App\Exceptions\Auth\RegisterOnShopFirstException;
use App\Exceptions\Auth\UnverifiedEmailException;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Top-level orchestrator for social auth flows. Wraps the matcher with
 * persistence + events + Pelican sync + throwing the right exception for the
 * controller to render.
 *
 * Deliberately independent of HTTP: takes a SocialiteUser or a Request and
 * returns a CallbackOutcome or a redirect URL. Keeps controller thin.
 */
class SocialAuthService
{
    public function __construct(
        private readonly AuthProviderRegistry $registry,
        private readonly SocialUserMatcher $matcher,
        private readonly SettingsService $settings,
        private readonly PelicanApplicationService $pelican,
    ) {}

    /**
     * Build the provider's authorize URL. Configures Socialite at runtime
     * from the DB-stored provider config and returns the URL — the controller
     * returns that as an HTTP redirect.
     *
     * `prompt=consent` is forced on every redirect so that providers (notably
     * Laravel Passport on the Shop side, plus Google / Discord) ALWAYS show
     * their consent screen. Without it, a user who unlinks then re-links the
     * provider sees a silent same-window redirect — the provider still has a
     * session cookie + a previously-approved client grant, so it bounces
     * straight back without asking. The papercut is real enough that users
     * report "the connect button does nothing visible" when it actually did
     * the round-trip in 200ms. Safe with all providers we support: standard
     * OAuth2 ignores unknown query params.
     */
    public function redirectUrl(string $provider): string
    {
        if (! $this->registry->isEnabled($provider)) {
            throw new ProviderDisabledException();
        }

        $this->registry->configureSocialite($provider);

        return Socialite::driver($this->registry->socialiteDriver($provider))
            ->with(['prompt' => 'consent'])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Run the callback flow end-to-end:
     *   - fetch the SocialiteUser
     *   - resolve via SocialUserMatcher
     *   - persist / link / reject as instructed
     *   - dispatch OAuthProviderLinked when a new identity is attached
     *   - sync the local email → Pelican when a canonical IdP email has changed
     *   - return CallbackOutcome
     */
    public function handleCallback(string $provider, Request $request): CallbackOutcome
    {
        if (! $this->registry->isEnabled($provider)) {
            throw new ProviderDisabledException();
        }

        $this->registry->configureSocialite($provider);

        $socialiteUser = Socialite::driver($this->registry->socialiteDriver($provider))->user();
        $matchResult = $this->matcher->match($provider, $socialiteUser);

        $user = match ($matchResult->action) {
            MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL => throw new UnverifiedEmailException($provider),
            MatchResult::ACTION_REJECT_REGISTER_ON_SHOP_FIRST => throw new RegisterOnShopFirstException(),
            MatchResult::ACTION_MATCH_BY_IDENTITY => $this->touchIdentity($matchResult->user, $provider, $socialiteUser),
            MatchResult::ACTION_MATCH_BY_EMAIL => $this->linkIdentity($matchResult->user, $provider, $socialiteUser, $request),
            MatchResult::ACTION_CREATE => $this->createAndLink($provider, $socialiteUser, $request),
        };

        if ($this->registry->isCanonical($provider)) {
            $this->syncCanonicalEmailToPelican($user, (string) $socialiteUser->getEmail());
        }

        $providerWasJustLinked = in_array(
            $matchResult->action,
            [MatchResult::ACTION_MATCH_BY_EMAIL, MatchResult::ACTION_CREATE],
            true,
        );

        // OAuth providers are treated as primary authentication — the provider
        // already authenticated the user (with its own 2FA if configured). We
        // skip Peregrine's TOTP challenge on OAuth callbacks to avoid a double
        // prompt. The admin can flip `auth_2fa_skip_oauth` to 'false' if they
        // want defence-in-depth.
        $skipOauth2fa = $this->settings->get('auth_2fa_skip_oauth', 'true') === 'true';

        return new CallbackOutcome(
            user: $user,
            requires2fa: ! $skipOauth2fa && $user->hasTwoFactor(),
            providerWasJustLinked: $providerWasJustLinked,
        );
    }

    /**
     * Unlink a provider from the current user. Plan §S7: block when this is
     * the user's only remaining login method.
     */
    public function unlink(User $user, string $provider, Request $request): void
    {
        if (! $this->registry->isSupported($provider)) {
            throw new ProviderDisabledException();
        }

        $identity = $user->oauthIdentities()->where('provider', $provider)->first();
        if ($identity === null) {
            return;
        }

        $hasPassword = ! empty($user->password);
        $hasOtherIdentity = $user->oauthIdentities()->where('provider', '<>', $provider)->exists();

        if (! $hasPassword && ! $hasOtherIdentity) {
            throw new LastLoginMethodException();
        }

        $identity->delete();

        event(new OAuthProviderUnlinked(
            user: $user,
            provider: $provider,
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        ));
    }

    /**
     * Update last_login_at + provider_email (in case the user changed their
     * email at the provider side). Does NOT sync the local email — that's
     * only the canonical IdPs' job (handled by syncCanonicalEmailToPelican).
     */
    private function touchIdentity(User $user, string $provider, SocialiteUser $socialiteUser): User
    {
        $user->oauthIdentities()
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $socialiteUser->getId())
            ->update([
                'provider_email' => (string) $socialiteUser->getEmail(),
                'last_login_at' => now(),
            ]);

        return $user;
    }

    private function linkIdentity(User $user, string $provider, SocialiteUser $socialiteUser, Request $request): User
    {
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => (string) $socialiteUser->getId(),
            'provider_email' => (string) $socialiteUser->getEmail(),
            'last_login_at' => now(),
        ]);

        event(new OAuthProviderLinked(
            user: $user,
            provider: $provider,
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        ));

        return $user;
    }

    private function createAndLink(string $provider, SocialiteUser $socialiteUser, Request $request): User
    {
        $email = (string) $socialiteUser->getEmail();
        $name = (string) ($socialiteUser->getName() ?: $email);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'locale' => $request->header('Accept-Language', 'en') && str_starts_with((string) $request->header('Accept-Language'), 'fr') ? 'fr' : 'en',
        ]);

        // Provision the matching Pelican account in the background so the
        // OAuth callback returns immediately. The job is idempotent and
        // unique-by-user, so duplicate dispatches (e.g. login backfill firing
        // alongside this) collapse to a single execution.
        LinkPelicanAccountJob::dispatch($user->id, "oauth:{$provider}");

        return $this->linkIdentity($user, $provider, $socialiteUser, $request);
    }

    /**
     * Canonical IdPs (shop, paymenter) are the source of truth for the user's
     * email. When the canonical provider tells us the email changed, propagate
     * the change to the local user AND (if they have a Pelican account) push
     * it to Pelican via the Application API — Pelican is NOT a source of truth
     * for the email.
     *
     * Conflict policy: if another local user already owns the new email
     * (typical when the user has a stale duplicate account, or the email was
     * registered locally first), we SKIP the local update entirely — both DB
     * (unique constraint would crash the callback) AND Pelican (same constraint
     * applies there). Login still succeeds with the old local email; the
     * conflict is logged so an admin can merge the duplicate.
     */
    private function syncCanonicalEmailToPelican(User $user, string $newEmail): void
    {
        if ($newEmail === '' || strtolower($user->email) === strtolower($newEmail)) {
            return;
        }

        $conflictingUserId = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($newEmail)])
            ->where('id', '<>', $user->id)
            ->value('id');

        if ($conflictingUserId !== null) {
            \Illuminate\Support\Facades\Log::warning('Canonical email sync skipped — collision with another local user', [
                'user_id' => $user->id,
                'user_current_email' => $user->email,
                'provider_email' => $newEmail,
                'conflicting_local_user_id' => $conflictingUserId,
                'hint' => 'Merge or delete the duplicate user via the admin panel to allow the email sync.',
            ]);

            return;
        }

        $user->forceFill(['email' => $newEmail])->save();

        if ($user->pelican_user_id === null) {
            return;
        }

        try {
            $this->pelican->changeUserEmail($user->pelican_user_id, $newEmail);
        } catch (\Throwable $e) {
            // Don't fail login — a Pelican outage must not block users signing
            // into Peregrine itself. But DO log details so the desync is
            // visible (the previous silent catch hid a real PATCH validation
            // failure for months — Pelican rejects partial-email PATCHes).
            \Illuminate\Support\Facades\Log::warning('Pelican email sync failed during canonical login', [
                'user_id' => $user->id,
                'pelican_user_id' => $user->pelican_user_id,
                'new_email' => $newEmail,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'response_body' => method_exists($e, 'response') && $e->response ? (string) $e->response->body() : null,
            ]);
        }
    }
}
