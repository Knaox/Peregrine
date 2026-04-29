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
}
