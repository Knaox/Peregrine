<?php

namespace App\Providers;

use App\Events\AdminActionPerformed;
use App\Events\OAuthProviderLinked;
use App\Events\OAuthProviderUnlinked;
use App\Events\RecoveryCodesRegenerated;
use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Listeners\LogAdminAction;
use App\Listeners\SendOAuthProviderLinkedNotification;
use App\Listeners\SendOAuthProviderUnlinkedNotification;
use App\Listeners\SendRecoveryCodesRegeneratedNotification;
use App\Listeners\SendTwoFactorDisabledNotification;
use App\Listeners\SendTwoFactorEnabledNotification;
use App\Services\Auth\ShopSocialiteProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Manager\SocialiteWasCalled;

class SocialAuthServiceProvider extends ServiceProvider
{
    /**
     * Wires up auth-related event listeners + Socialite driver extensions.
     *
     * Discord isn't part of Socialite core, so the SocialiteProviders package
     * fires SocialiteWasCalled when any driver is resolved — we hook in and
     * attach the Discord driver. Google + LinkedIn-OpenID are core drivers.
     *
     * The 'shop' custom driver is registered here via Socialite::extend().
     * Its config is injected at runtime from the DB settings by
     * AuthProviderRegistry::configureSocialite('shop') before each driver()
     * call.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [
            \SocialiteProviders\Discord\DiscordExtendSocialite::class,
            'handle',
        ]);

        Event::listen(TwoFactorEnabled::class, SendTwoFactorEnabledNotification::class);
        Event::listen(TwoFactorDisabled::class, SendTwoFactorDisabledNotification::class);
        Event::listen(RecoveryCodesRegenerated::class, SendRecoveryCodesRegeneratedNotification::class);

        Event::listen(AdminActionPerformed::class, LogAdminAction::class);

        Event::listen(OAuthProviderLinked::class, SendOAuthProviderLinkedNotification::class);
        Event::listen(OAuthProviderUnlinked::class, SendOAuthProviderUnlinkedNotification::class);

        $this->app->make(SocialiteFactory::class)->extend('shop', function ($app) {
            $cfg = (array) config('services.shop', []);

            /** @var \Laravel\Socialite\Contracts\Factory $socialite */
            $socialite = $app->make(SocialiteFactory::class);

            /** @var ShopSocialiteProvider $provider */
            $provider = $socialite->buildProvider(ShopSocialiteProvider::class, $cfg);

            return $provider->withExtraConfig([
                'authorize_url' => $cfg['authorize_url'] ?? '',
                'token_url' => $cfg['token_url'] ?? '',
                'user_url' => $cfg['user_url'] ?? '',
            ]);
        });
    }
}
