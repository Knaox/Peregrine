<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectAdminToFrontendLogin;
use App\Http\Middleware\SetUserLocale;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use App\Services\ThemeService;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // No ->login() — admins must sign in via the React /login page
            // (single source of truth for multi-provider auth + 2FA + OAuth
            // canonical IdP redirect). The auth middleware below redirects
            // unauthenticated /admin/* hits to /login with the original URL
            // preserved via ?redirect_to=.
            ->brandName('Peregrine')
            ->brandLogo(asset('images/logo.webp'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/favicon.ico'))
            ->colors([
                'primary' => Color::hex(app(ThemeService::class)->getPrimaryColor()),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => __('admin.navigation.player_panel'))
                    ->url('/')
                    ->icon('heroicon-o-arrow-left'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationItems([
                NavigationItem::make('player-panel')
                    ->label(fn () => __('admin.navigation.player_panel'))
                    ->url('/')
                    ->icon('heroicon-o-arrow-left')
                    ->sort(100),
            ])
            // Indexed-array form so matching happens by KEY (the fixed
            // 'Servers'/'Integrations'/'Settings' string returned by each
            // Resource/Page::getNavigationGroup()) while the LABEL is a
            // closure that resolves the i18n key per request. Without the
            // indexed form, matching falls back to label comparison and a
            // French label ("Serveurs & Pelican") would no longer match the
            // English key 'Servers' coming from the Resource.
            ->navigationGroups([
                'Servers' => NavigationGroup::make()
                    ->label(fn () => __('admin.navigation.groups.servers'))
                    ->icon('heroicon-o-server-stack'),
                'Integrations' => NavigationGroup::make()
                    ->label(fn () => __('admin.navigation.groups.integrations'))
                    ->icon('heroicon-o-link'),
                'Settings' => NavigationGroup::make()
                    ->label(fn () => __('admin.navigation.groups.settings'))
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Re-apply the user-locale middleware here. The global stack
                // already sets it for /api/* and /admin/*, but Filament boot
                // happens before the global middleware in some hot paths
                // (cached routes, queue listeners hitting Filament). Belt
                // and braces.
                SetUserLocale::class,
            ])
            ->authMiddleware([
                RedirectAdminToFrontendLogin::class,
            ]);

        // Let active plugins contribute Filament resources/pages BEFORE
        // the panel finalises its route list. Without this, plugin admin
        // pages 404 in production : the plugin's ServiceProvider boots
        // AFTER the panel and adding resources later doesn't rebuild
        // routes.
        return app(\App\Services\PluginManager::class)->contributeToFilamentPanel($panel);
    }
}
