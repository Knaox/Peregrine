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
}
