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
 * Filament section for the Paymenter OAuth canonical identity provider.
 * Extracted from AuthSettingsFormSchema to keep that schema under 300
 * lines. Mutually exclusive with the Shop provider — both are canonical
 * identity stores and only one can be active at a time.
 */
final class PaymenterProviderSection
{
    public static function make(string $redirectUri): Section
    {
        return Section::make(__('admin.auth_form.paymenter.section'))
            ->description(__('admin.auth_form.paymenter.description'))
            ->icon('heroicon-o-credit-card')
            ->schema([
                Toggle::make('auth_paymenter_enabled')->label(__('admin.auth_form.paymenter.enable')),

                TextInput::make('auth_paymenter_base_url')
                    ->label(__('admin.auth_form.paymenter.base_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com')
                    ->helperText(__('admin.auth_form.paymenter.base_url_helper')),

                TextInput::make('auth_paymenter_client_id')
                    ->label(__('admin.auth_form.paymenter.client_id'))
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.paymenter.client_id_helper')),

                TextInput::make('auth_paymenter_client_secret')
                    ->label(__('admin.auth_form.paymenter.client_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.paymenter.client_secret_helper')),

                TextInput::make('auth_paymenter_redirect_uri')
                    ->label(__('admin.auth_form.paymenter.redirect'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(__('admin.auth_form.paymenter.redirect_helper'))
                    ->suffixAction(
                        Action::make('resetPaymenterRedirect')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__('admin.auth_form.paymenter.reset_tooltip'))
                            ->color('gray')
                            ->action(function (Set $set) use ($redirectUri): void {
                                $set('auth_paymenter_redirect_uri', $redirectUri);
                                Notification::make()
                                    ->title(__('admin.auth_form.paymenter.reset_notification_title'))
                                    ->body(__('admin.auth_form.paymenter.reset_notification_body'))
                                    ->success()
                                    ->send();
                            }),
                    ),

                TextInput::make('auth_paymenter_register_url')
                    ->label(__('admin.auth_form.paymenter.register_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://billing.example.com/register')
                    ->helperText(__('admin.auth_form.paymenter.register_url_helper')),

                FileUpload::make('auth_paymenter_logo_path')
                    ->label(__('admin.auth_form.paymenter.logo'))
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
                    ->helperText(__('admin.auth_form.paymenter.logo_helper')),
            ]);
    }
}
