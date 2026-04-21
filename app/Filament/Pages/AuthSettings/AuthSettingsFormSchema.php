<?php

namespace App\Filament\Pages\AuthSettings;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * Form schema for the "Auth & Security" settings page.
 *
 * Étape B populates only the 2FA section. Shop + social provider sections
 * land in étape C — add them here as more static methods so the page stays
 * < 300 lines.
 */
final class AuthSettingsFormSchema
{
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
}
