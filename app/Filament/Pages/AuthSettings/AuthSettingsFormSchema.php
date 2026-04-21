<?php

namespace App\Filament\Pages\AuthSettings;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * Form schema for the "Auth & Security" settings page. Each section is
 * a static method so the page class stays light.
 */
final class AuthSettingsFormSchema
{
    public static function general(): Section
    {
        return Section::make('General')
            ->description('Global sign-in behaviour. Local auth + local registration can be toggled independently from the OAuth providers below.')
            ->icon('heroicon-o-key')
            ->schema([
                Toggle::make('auth_local_enabled')
                    ->label('Allow local email/password login')
                    ->helperText('Users created locally (or via OAuth with a password set) can sign in with email + password.'),
                Toggle::make('auth_local_registration_enabled')
                    ->label('Allow local registration')
                    ->helperText('When off, /register is closed — users must come in via a Shop callback or a linked provider. Can stay on alongside Shop if you want both paths available.'),
            ]);
    }

    public static function twoFactor(): Section
    {
        return Section::make('Two-Factor Authentication')
            ->description('Controls availability of TOTP 2FA across the panel. Independent from provider OAuth settings.')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Toggle::make('auth_2fa_enabled')
                    ->label('Enable 2FA for all users')
                    ->helperText('Users can set up TOTP (Google Authenticator / Authy / 1Password) from their profile. Disabling this hides the setup UI but does not remove existing 2FA configuration.'),
                Toggle::make('auth_2fa_required_admins')
                    ->label('Require 2FA for admins')
                    ->helperText('Admins without 2FA will be blocked from /admin and admin API routes with a 403 redirecting them to the setup page. Turn this on only after you have set up 2FA on your own admin account — otherwise you lock yourself out.'),
            ]);
    }

    public static function shop(string $redirectUri): Section
    {
        return Section::make('Shop — BiomeBounty')
            ->description('Canonical identity provider. When enabled, user accounts are created from the Shop; email sync with Pelican is automatic.')
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Toggle::make('auth_shop_enabled')->label('Enable Shop as identity provider'),
                TextInput::make('auth_shop_client_id')->label('Client ID')->maxLength(255),
                TextInput::make('auth_shop_client_secret')
                    ->label('Client secret')
                    ->password()
                    ->revealable()
                    ->helperText('Leave blank to keep the stored value. Typing a new value replaces the encrypted envelope on save.'),
                TextInput::make('auth_shop_authorize_url')->label('Authorize URL')->url()->maxLength(255),
                TextInput::make('auth_shop_token_url')->label('Token URL')->url()->maxLength(255),
                TextInput::make('auth_shop_user_url')->label('User profile URL')->url()->maxLength(255),
                TextInput::make('auth_shop_register_url')
                    ->label('Shop register page URL (optional)')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://biomebounty.com/register')
                    ->helperText('When set, the "Create account" link on the login page sends visitors to the Shop\'s sign-up page instead of (or alongside) the local /register form. Leave empty to keep local-only behaviour.'),
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
                    ->helperText('Replaces the default shop icon on the login button. SVG, PNG, JPEG, WebP or ICO (favicon), square preferred (max 1MB). Leave empty to keep the default icon.'),
                Placeholder::make('auth_shop_redirect_display')
                    ->label('Redirect URI (copy this into the Shop\'s OAuth app)')
                    ->content($redirectUri),
            ]);
    }

    /**
     * Reusable builder for Google / Discord / LinkedIn — same three-field
     * shape with the readonly redirect URI placeholder for the admin to copy
     * into the provider's developer console.
     */
    public static function socialProvider(string $providerId, string $label, string $icon, string $redirectUri): Section
    {
        return Section::make($label)
            ->icon($icon)
            ->collapsed()
            ->schema([
                Toggle::make("auth_providers_{$providerId}_enabled")->label("Enable {$label}"),
                TextInput::make("auth_providers_{$providerId}_client_id")->label('Client ID')->maxLength(255),
                TextInput::make("auth_providers_{$providerId}_client_secret")
                    ->label('Client secret')
                    ->password()
                    ->revealable()
                    ->helperText('Leave blank to keep the stored value.'),
                Placeholder::make("auth_providers_{$providerId}_redirect_display")
                    ->label('Redirect URI')
                    ->content($redirectUri),
            ]);
    }

    public static function safety(): Section
    {
        return Section::make('Safety')
            ->description('Acknowledge risks before applying destructive changes.')
            ->icon('heroicon-o-exclamation-triangle')
            ->collapsed()
            ->schema([
                Toggle::make('acknowledge_disable_risk')
                    ->label('I understand that disabling a provider may lock out users who have no other sign-in method')
                    ->helperText('Required to save when you are turning OFF a provider that currently has exclusive users (no password, no other linked identity).'),
            ]);
    }
}
