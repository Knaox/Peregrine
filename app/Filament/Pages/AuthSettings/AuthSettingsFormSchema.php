<?php

namespace App\Filament\Pages\AuthSettings;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Form schema for the "Auth & Security" admin page.
 *
 * Top-level Tabs split the page by concern (General / Shop / Paymenter /
 * Social / Safety) so admins land directly on what they need to edit.
 *
 * Each OAuth provider exposes ALL of its endpoint URLs as editable fields,
 * including the Redirect URI — earlier versions left it read-only, which
 * trapped operators whose installer wrote the wrong host (e.g. an internal
 * Docker IP captured before a reverse proxy was put in front).
 */
final class AuthSettingsFormSchema
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function tabs(string $shopRedirect, string $paymenterRedirect, string $googleRedirect, string $discordRedirect, string $linkedinRedirect): array
    {
        return [
            Tabs::make('auth_settings_tabs')
                ->tabs([
                    Tab::make('General')
                        ->icon('heroicon-o-home')
                        ->schema([self::general(), self::twoFactor()]),
                    Tab::make('Shop')
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([self::shop($shopRedirect)]),
                    Tab::make('Paymenter')
                        ->icon('heroicon-o-credit-card')
                        ->schema([self::paymenter($paymenterRedirect)]),
                    Tab::make('Social')
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            self::socialProvider('google', 'Google', 'heroicon-o-globe-alt', $googleRedirect),
                            self::socialProvider('discord', 'Discord', 'heroicon-o-chat-bubble-left-right', $discordRedirect),
                            self::socialProvider('linkedin', 'LinkedIn', 'heroicon-o-briefcase', $linkedinRedirect),
                        ]),
                    Tab::make('Safety')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->schema([self::safety()]),
                ])
                ->persistTabInQueryString('authTab'),
        ];
    }

    public static function general(): Section
    {
        return Section::make('Local sign-in')
            ->description('Email/password and self-registration. These can stay on alongside an OAuth provider if you want both paths available, or be turned off entirely to force OAuth-only access.')
            ->icon('heroicon-o-key')
            ->schema([
                Toggle::make('auth_local_enabled')
                    ->label('Allow local email/password login')
                    ->helperText('Users created locally (or via OAuth with a password set) can sign in with email + password.'),
                Toggle::make('auth_local_registration_enabled')
                    ->label('Allow local registration')
                    ->helperText('When off, /register is closed — users must come in via a Shop callback or a linked provider. Forced off automatically when a canonical IdP (Shop or Paymenter) is enabled.'),
            ]);
    }

    public static function twoFactor(): Section
    {
        return Section::make('Two-Factor Authentication')
            ->description('TOTP (Google Authenticator / Authy / 1Password) availability across the panel.')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Toggle::make('auth_2fa_enabled')
                    ->label('Enable 2FA for all users')
                    ->helperText('Users can set up TOTP from their profile. Disabling this hides the setup UI but does NOT remove existing 2FA secrets — re-enabling it later restores access without recovery codes.'),
                Toggle::make('auth_2fa_required_admins')
                    ->label('Require 2FA for admins')
                    ->helperText('⚠ Admins without 2FA will be blocked from /admin and admin API routes (403 → setup page). Turn this on AFTER you have set up 2FA on your own admin account, otherwise you lock yourself out.'),
            ]);
    }

    public static function shop(string $redirectUri): Section
    {
        return Section::make('Shop — BiomeBounty')
            ->description('Canonical identity provider. When enabled, accounts are auto-created on first OAuth login, email is synced to Pelican, and local registration is forced off. Mutually exclusive with Paymenter.')
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Toggle::make('auth_shop_enabled')->label('Enable Shop as identity provider'),

                TextInput::make('auth_shop_client_id')
                    ->label('Client ID')
                    ->maxLength(255)
                    ->placeholder('019dc559-4759-71ea-a12b-7826b714dd9c')
                    ->helperText('Public identifier of the OAuth client. Created in the Shop\'s admin (Passport: artisan passport:client → "Authorization Code"). Copy the "Client ID" here.'),

                TextInput::make('auth_shop_client_secret')
                    ->label('Client secret')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText('Private secret paired with the Client ID. Leave blank to keep the stored value — typing a new value replaces the encrypted envelope on save.'),

                TextInput::make('auth_shop_authorize_url')
                    ->label('Authorize URL')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/oauth/authorize')
                    ->helperText('Where Peregrine redirects the browser so the user can grant access. Laravel Passport default: <strong>https://&lt;shop&gt;/oauth/authorize</strong>.'),

                TextInput::make('auth_shop_token_url')
                    ->label('Token URL')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/oauth/token')
                    ->helperText('Server-to-server endpoint Peregrine POSTs the auth code to in exchange for an access token. Passport default: <strong>https://&lt;shop&gt;/oauth/token</strong>.'),

                TextInput::make('auth_shop_user_url')
                    ->label('User profile URL')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/api/user')
                    ->helperText('Endpoint Peregrine calls with the access token to fetch the user\'s email + name. Passport default: <strong>https://&lt;shop&gt;/api/user</strong>.'),

                TextInput::make('auth_shop_redirect_uri')
                    ->label('Redirect URI')
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(
                        'Where the OAuth server sends the user back. <strong>Must match EXACTLY</strong> the URL registered in the Shop\'s OAuth client config '
                        . '(Passport: <code>oauth_clients.redirect</code>). If you installed Peregrine before setting up your reverse proxy, this may still contain a stale internal IP — '
                        . 'click "Reset to default" to use APP_URL.'
                    )
                    ->suffixAction(
                        Action::make('resetShopRedirect')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Reset to APP_URL default')
                            ->color('gray')
                            ->action(function (Set $set) use ($redirectUri): void {
                                $set('auth_shop_redirect_uri', $redirectUri);
                                Notification::make()
                                    ->title('Reset to APP_URL default')
                                    ->body('Don\'t forget to update <code>oauth_clients.redirect</code> on the Shop side too — and click Save.')
                                    ->success()
                                    ->send();
                            }),
                    ),

                TextInput::make('auth_shop_register_url')
                    ->label('Shop register page URL (optional)')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/register')
                    ->helperText('Surface a "Create account on the Shop" link on Peregrine\'s login page. Leave empty to keep the local /register form only.'),

                FileUpload::make('auth_shop_logo_path')
                    ->label('Custom button logo (optional)')
                    ->image()
                    ->directory('branding/oauth')
                    ->disk('public')
                    ->acceptedFileTypes([
                        'image/svg+xml',
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                        'image/x-icon',
                        'image/vnd.microsoft.icon',
                    ])
                    ->maxSize(1024)
                    ->helperText('Replaces the default shop icon on the login button. SVG / PNG / JPEG / WebP / ICO, square preferred (max 1MB).'),
            ]);
    }

    /**
     * Reusable builder for Google / Discord / LinkedIn — same shape with
     * an editable redirect URI and a reset-to-default action.
     */
    public static function socialProvider(string $providerId, string $label, string $icon, string $redirectUri): Section
    {
        return Section::make($label)
            ->icon($icon)
            ->collapsible()
            ->collapsed()
            ->schema([
                Toggle::make("auth_providers_{$providerId}_enabled")->label("Enable {$label}"),

                TextInput::make("auth_providers_{$providerId}_client_id")
                    ->label('Client ID')
                    ->maxLength(255)
                    ->helperText("Created in the {$label} developer console (OAuth 2.0 Client / Application)."),

                TextInput::make("auth_providers_{$providerId}_client_secret")
                    ->label('Client secret')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText('Leave blank to keep the stored value.'),

                TextInput::make("auth_providers_{$providerId}_redirect_uri")
                    ->label('Redirect URI')
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText("Paste this URL into the {$label} app's authorized redirect list. Must match EXACTLY (scheme, host, path).")
                    ->suffixAction(
                        Action::make("reset{$providerId}Redirect")
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Reset to APP_URL default')
                            ->color('gray')
                            ->action(function (Set $set) use ($providerId, $redirectUri): void {
                                $set("auth_providers_{$providerId}_redirect_uri", $redirectUri);
                            }),
                    ),
            ]);
    }

    public static function paymenter(string $redirectUri): Section
    {
        return Section::make('Paymenter')
            ->description('Open-source billing platform (paymenter.org). Acts as a canonical identity provider — auto-creates local users, syncs email to Pelican, surfaces a register URL. Mutually exclusive with Shop.')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Toggle::make('auth_paymenter_enabled')->label('Enable Paymenter as identity provider'),

                TextInput::make('auth_paymenter_base_url')
                    ->label('Paymenter base URL')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com')
                    ->helperText('Root URL of your Paymenter install (no trailing slash). Peregrine derives <code>/oauth/authorize</code>, <code>/api/oauth/token</code> and <code>/api/me</code> from this base — you don\'t need to fill them separately.'),

                TextInput::make('auth_paymenter_client_id')
                    ->label('Client ID')
                    ->maxLength(255)
                    ->helperText('Created in Paymenter admin (OAuth Clients section). Copy the "Client ID" here.'),

                TextInput::make('auth_paymenter_client_secret')
                    ->label('Client secret')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText('Leave blank to keep the stored value. Typing a new value replaces the encrypted envelope on save.'),

                TextInput::make('auth_paymenter_redirect_uri')
                    ->label('Redirect URI')
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(
                        'Where the OAuth server sends the user back. <strong>Must match EXACTLY</strong> the URL configured in Paymenter\'s OAuth Client. '
                        . 'If you installed before setting up your reverse proxy, this may contain a stale internal IP — click "Reset to default" to use APP_URL.'
                    )
                    ->suffixAction(
                        Action::make('resetPaymenterRedirect')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Reset to APP_URL default')
                            ->color('gray')
                            ->action(function (Set $set) use ($redirectUri): void {
                                $set('auth_paymenter_redirect_uri', $redirectUri);
                                Notification::make()
                                    ->title('Reset to APP_URL default')
                                    ->body('Don\'t forget to update Paymenter\'s OAuth Client redirect URI to match — and click Save.')
                                    ->success()
                                    ->send();
                            }),
                    ),

                TextInput::make('auth_paymenter_register_url')
                    ->label('Paymenter register page URL (optional)')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com/register')
                    ->helperText('Surface a "Create account on Paymenter" link on Peregrine\'s login page. Leave empty to keep local-only.'),

                FileUpload::make('auth_paymenter_logo_path')
                    ->label('Custom button logo (optional)')
                    ->image()
                    ->directory('branding/oauth')
                    ->disk('public')
                    ->acceptedFileTypes([
                        'image/svg+xml',
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                        'image/x-icon',
                        'image/vnd.microsoft.icon',
                    ])
                    ->maxSize(1024)
                    ->helperText('Replaces the default Paymenter icon on the login button. SVG / PNG / JPEG / WebP / ICO, square preferred (max 1MB).'),
            ]);
    }

    public static function safety(): Section
    {
        return Section::make('Lock-out safety')
            ->description('Required acknowledgement when disabling a provider that has exclusive users (no password, no other linked identity). Prevents silent account lock-outs.')
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                Toggle::make('acknowledge_disable_risk')
                    ->label('I understand that disabling a provider may lock out users who have no other sign-in method')
                    ->helperText('Required to save when you are turning OFF a provider that currently has exclusive users.'),
            ]);
    }
}
