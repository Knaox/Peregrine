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
        return Sections\ShopProviderSection::make($redirectUri);
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
        return Sections\PaymenterProviderSection::make($redirectUri);
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
