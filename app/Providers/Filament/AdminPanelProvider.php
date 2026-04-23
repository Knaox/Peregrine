<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectAdminToFrontendLogin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
        return $panel
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
                    ->label('Player panel')
                    ->url('/')
                    ->icon('heroicon-o-arrow-left'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationItems([
                NavigationItem::make('Player panel')
                    ->url('/')
                    ->icon('heroicon-o-arrow-left')
                    ->sort(100),
            ])
            ->navigationGroups([
                'Servers',
                'Pelican',
                'Settings',
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
            ])
            ->authMiddleware([
                RedirectAdminToFrontendLogin::class,
            ]);
    }
}
