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
 * Filament section for the Shop (BiomeBounty) OAuth canonical identity
 * provider. Extracted from AuthSettingsFormSchema to keep that schema
 * file under the 300-line plafond CLAUDE.md.
 */
final class ShopProviderSection
{
    public static function make(string $redirectUri): Section
    {
        return Section::make(__('admin.auth_form.shop.section'))
            ->description(__('admin.auth_form.shop.description'))
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Toggle::make('auth_shop_enabled')->label(__('admin.auth_form.shop.enable')),

                TextInput::make('auth_shop_client_id')
                    ->label(__('admin.auth_form.shop.client_id'))
                    ->maxLength(255)
                    ->placeholder('019dc559-4759-71ea-a12b-7826b714dd9c')
                    ->helperText(__('admin.auth_form.shop.client_id_helper')),

                TextInput::make('auth_shop_client_secret')
                    ->label(__('admin.auth_form.shop.client_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.shop.client_secret_helper')),

                TextInput::make('auth_shop_authorize_url')
                    ->label(__('admin.auth_form.shop.authorize_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/oauth/authorize')
                    ->helperText(__('admin.auth_form.shop.authorize_url_helper')),

                TextInput::make('auth_shop_token_url')
                    ->label(__('admin.auth_form.shop.token_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/oauth/token')
                    ->helperText(__('admin.auth_form.shop.token_url_helper')),

                TextInput::make('auth_shop_user_url')
                    ->label(__('admin.auth_form.shop.user_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/api/user')
                    ->helperText(__('admin.auth_form.shop.user_url_helper')),

                TextInput::make('auth_shop_redirect_uri')
                    ->label(__('admin.auth_form.shop.redirect'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(__('admin.auth_form.shop.redirect_helper'))
                    ->suffixAction(
                        Action::make('resetShopRedirect')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__('admin.auth_form.shop.reset_tooltip'))
                            ->color('gray')
                            ->action(function (Set $set) use ($redirectUri): void {
                                $set('auth_shop_redirect_uri', $redirectUri);
                                Notification::make()
                                    ->title(__('admin.auth_form.shop.reset_notification_title'))
                                    ->body(__('admin.auth_form.shop.reset_notification_body'))
                                    ->success()
                                    ->send();
                            }),
                    ),

                TextInput::make('auth_shop_register_url')
                    ->label(__('admin.auth_form.shop.register_url'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://shop.example.com/register')
                    ->helperText(__('admin.auth_form.shop.register_url_helper')),

                FileUpload::make('auth_shop_logo_path')
                    ->label(__('admin.auth_form.shop.logo'))
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
                    ->helperText(__('admin.auth_form.shop.logo_helper')),
            ]);
    }
}
