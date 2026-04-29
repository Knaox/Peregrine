<?php

namespace App\Filament\Pages\AuthSettings\Sections;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Filament section for the WHMCS OpenID Connect canonical identity
 * provider. WHMCS exposes a native OIDC provider under
 * Configuration → System Settings → OpenID Connect — admins generate
 * client credentials there and paste them here.
 *
 * Mutually exclusive with the Shop and Paymenter providers — all three
 * are canonical identity stores and only one can be active at a time
 * (AuthSettings::save() enforces).
 */
final class WhmcsProviderSection
{
    public static function make(string $redirectUri): Section
    {
        return Section::make(__('admin.auth_form.whmcs.section'))
            ->description(__('admin.auth_form.whmcs.description'))
            ->icon('heroicon-o-banknotes')
            ->schema([
                Toggle::make('auth_whmcs_enabled')->label(__('admin.auth_form.whmcs.enable')),

                TextInput::make('auth_whmcs_base_url')
                    ->label(__('admin.auth_form.whmcs.base_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com')
                    ->helperText(__('admin.auth_form.whmcs.base_url_helper')),

                TextInput::make('auth_whmcs_client_id')
                    ->label(__('admin.auth_form.whmcs.client_id'))
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.whmcs.client_id_helper')),

                TextInput::make('auth_whmcs_client_secret')
                    ->label(__('admin.auth_form.whmcs.client_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.whmcs.client_secret_helper')),

                TextInput::make('auth_whmcs_redirect_uri')
                    ->label(__('admin.auth_form.whmcs.redirect'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(__('admin.auth_form.whmcs.redirect_helper'))
                    ->suffixAction(
                        Action::make('resetWhmcsRedirect')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__('admin.auth_form.whmcs.reset_tooltip'))
                            ->color('gray')
                            ->action(function (Set $set) use ($redirectUri): void {
                                $set('auth_whmcs_redirect_uri', $redirectUri);
                                Notification::make()
                                    ->title(__('admin.auth_form.whmcs.reset_notification_title'))
                                    ->body(__('admin.auth_form.whmcs.reset_notification_body'))
                                    ->success()
                                    ->send();
                            }),
                    ),

                TextInput::make('auth_whmcs_register_url')
                    ->label(__('admin.auth_form.whmcs.register_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com/register.php')
                    ->helperText(__('admin.auth_form.whmcs.register_url_helper')),

                FileUpload::make('auth_whmcs_logo_path')
                    ->label(__('admin.auth_form.whmcs.logo'))
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
                    ->helperText(__('admin.auth_form.whmcs.logo_helper')),
            ]);
    }
}
