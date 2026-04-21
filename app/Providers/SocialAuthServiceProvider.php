<?php

namespace App\Providers;

use App\Events\AdminActionPerformed;
use App\Events\RecoveryCodesRegenerated;
use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Listeners\LogAdminAction;
use App\Listeners\SendRecoveryCodesRegeneratedNotification;
use App\Listeners\SendTwoFactorDisabledNotification;
use App\Listeners\SendTwoFactorEnabledNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
     * The 'shop' custom driver is wired in étape C via Socialite::extend().
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

        // Étape C will add here:
        // \Laravel\Socialite\Facades\Socialite::extend('shop',
        //     fn ($app) => new \App\Services\Auth\ShopSocialiteProvider(...));
    }
}
