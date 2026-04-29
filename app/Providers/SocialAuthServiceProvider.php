<?php

namespace App\Providers;

use App\Services\Auth\PaymenterSocialiteProvider;
use App\Services\Auth\ShopSocialiteProvider;
use App\Services\Auth\WhmcsSocialiteProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Manager\SocialiteWasCalled;

class SocialAuthServiceProvider extends ServiceProvider
{
    /**
     * Wires up auth-related Socialite driver extensions.
     *
     * Discord isn't part of Socialite core, so the SocialiteProviders package
     * fires SocialiteWasCalled when any driver is resolved — we hook in and
     * attach the Discord driver. Google + LinkedIn-OpenID are core drivers.
     *
     * The 'shop' + 'paymenter' + 'whmcs' custom drivers are registered here via
     * Socialite::extend(). Their config is injected at runtime from the DB
     * settings by AuthProviderRegistry::configureSocialite('shop') before
     * each driver() call.
     *
     * **Listener wiring lives in `app/Listeners/` itself** — Laravel 13
     * auto-discovers any class under that namespace whose `handle()` is
     * type-hinted with an event class (Send*Notification, LogAdminAction).
     * Adding `Event::listen(...)` here would register them a second time
     * and double-fire every email / log row.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [
            \SocialiteProviders\Discord\DiscordExtendSocialite::class,
            'handle',
        ]);

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

        $this->app->make(SocialiteFactory::class)->extend('paymenter', function ($app) {
            $cfg = (array) config('services.paymenter', []);

            /** @var \Laravel\Socialite\Contracts\Factory $socialite */
            $socialite = $app->make(SocialiteFactory::class);

            /** @var PaymenterSocialiteProvider $provider */
            $provider = $socialite->buildProvider(PaymenterSocialiteProvider::class, $cfg);

            return $provider->withExtraConfig([
                'authorize_url' => $cfg['authorize_url'] ?? '',
                'token_url' => $cfg['token_url'] ?? '',
                'user_url' => $cfg['user_url'] ?? '',
            ]);
        });

        $this->app->make(SocialiteFactory::class)->extend('whmcs', function ($app) {
            $cfg = (array) config('services.whmcs', []);

            /** @var \Laravel\Socialite\Contracts\Factory $socialite */
            $socialite = $app->make(SocialiteFactory::class);

            /** @var WhmcsSocialiteProvider $provider */
            $provider = $socialite->buildProvider(WhmcsSocialiteProvider::class, $cfg);

            return $provider->withExtraConfig([
                'authorize_url' => $cfg['authorize_url'] ?? '',
                'token_url' => $cfg['token_url'] ?? '',
                'user_url' => $cfg['user_url'] ?? '',
            ]);
        });
    }
}
