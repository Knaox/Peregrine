<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class SettingsFormSchema
{
    public static function appearance(): Section
    {
        return Section::make('Appearance')
            ->description('Customize the look and feel of your panel.')
            ->icon('heroicon-o-paint-brush')
            ->schema([
                TextInput::make('app_name')
                    ->label('Application Name')
                    ->placeholder('Peregrine')
                    ->maxLength(255),
                Toggle::make('show_app_name')
                    ->label('Show application name in header')
                    ->helperText('Disable if your logo already contains the name.'),
                Select::make('default_locale')
                    ->label('Default language')
                    ->options([
                        'en' => 'English',
                        'fr' => 'Français',
                    ])
                    ->default('en')
                    ->required()
                    ->helperText('Used for newly registered users (until they pick their own) and for the SPA when no language is detected from the browser.'),
                Select::make('logo_height')
                    ->label('Logo Size')
                    ->options([
                        '24' => 'Small (24px)',
                        '32' => 'Medium (32px)',
                        '40' => 'Large (40px)',
                        '48' => 'Extra Large (48px)',
                        '56' => 'XXL (56px)',
                    ])
                    ->default('40'),
                FileUpload::make('logo_url')
                    ->label('Logo (dark mode)')
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(3072)
                    ->helperText('Default logo — shown in dark mode. Upload SVG, PNG, JPEG or WebP (max 3MB).'),
                FileUpload::make('logo_url_light')
                    ->label('Logo (light mode)')
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(3072)
                    ->helperText('Optional — shown to users in light mode. If empty, the dark-mode logo is used for both.'),
                FileUpload::make('favicon_url')
                    ->label('Favicon')
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                    ->maxSize(1024)
                    ->helperText('Upload SVG, PNG or ICO (max 1MB).'),
                Repeater::make('header_links')
                    ->label('Header Navigation Links')
                    ->helperText('Add custom links to the top navigation bar.')
                    ->schema([
                        TextInput::make('label')->label('Label (EN)')->required()->placeholder('Shop'),
                        TextInput::make('label_fr')->label('Label (FR)')->placeholder('Boutique'),
                        TextInput::make('url')->label('URL')->required()->placeholder('https://example.com'),
                        Select::make('icon')->label('Icon')->options([
                            'none' => 'No icon', 'home' => 'Home', 'shopping-bag' => 'Shop',
                            'ticket' => 'Ticket', 'user' => 'User', 'cog' => 'Settings',
                            'chat' => 'Chat / Discord', 'book' => 'Documentation', 'globe' => 'Website',
                            'server' => 'Server', 'shield' => 'Security', 'heart' => 'Donate',
                            'star' => 'Premium', 'link' => 'Link',
                        ])->default('none'),
                        Toggle::make('new_tab')->label('New tab')->default(true),
                    ])->columns(5)->reorderable()->defaultItems(0),
            ])->columns(1);
    }

    public static function pelican(): Section
    {
        return Section::make('Pelican')
            ->description('Configure the connection to your Pelican panel.')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                TextInput::make('pelican_url')->label('Pelican URL')->placeholder('https://panel.example.com')->url()->maxLength(255),
                TextInput::make('pelican_admin_api_key')->label('Admin API Key (Application API)')->password()->revealable()->maxLength(255)
                    ->helperText('Application API key (papp_...) for server provisioning.'),
                TextInput::make('pelican_client_api_key')->label('Client API Key')->password()->revealable()->maxLength(255)
                    ->helperText('Client API key (pacc_...) for console, files and power.'),
            ])->columns(1);
    }

    public static function smtp(): Section
    {
        return Section::make('SMTP / Email')
            ->description('Configure the mail server used to send emails (invitations, notifications).')
            ->icon('heroicon-o-envelope')
            ->collapsible()
            ->schema([
                Select::make('mail_mailer')
                    ->label('Mail Driver')
                    ->options([
                        'smtp' => 'SMTP',
                        'log' => 'Log (development only)',
                        'sendmail' => 'Sendmail',
                    ])
                    ->default('smtp')
                    ->live(),
                TextInput::make('mail_host')
                    ->label('SMTP Host')
                    ->placeholder('mail.example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_port')
                    ->label('SMTP Port')
                    ->placeholder('587')
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                Select::make('mail_encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS (recommended)',
                        'ssl' => 'SSL',
                        '' => 'None',
                    ])
                    ->default('tls')
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_username')
                    ->label('SMTP Username')
                    ->placeholder('noreply@example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_password')
                    ->label('SMTP Password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_from_address')
                    ->label('From Address')
                    ->placeholder('noreply@example.com')
                    ->email()
                    ->maxLength(255),
                TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->placeholder('Peregrine')
                    ->maxLength(255),
            ])->columns(2);
    }

    // Bridge configuration moved to its dedicated page (BridgeSettings).
    // The Bridge module now manages its own toggle, shared HMAC secret with
    // the Shop, and (eventually) the Stripe webhook secret. Keeping a second
    // toggle here would create two sources of truth.

    public static function developer(): Section
    {
        return Section::make('Developer')
            ->description('Low-level toggles. Leave debug mode OFF in production — when enabled, full PHP stack traces are exposed to clients on errors.')
            ->icon('heroicon-o-bug-ant')
            ->collapsible()->collapsed()
            ->schema([
                Toggle::make('app_debug')
                    ->label('Enable debug mode (APP_DEBUG)')
                    ->helperText('Writes APP_DEBUG to your .env. Restart php-fpm / docker container after toggling — Laravel caches the .env at boot.'),
            ])->columns(1);
    }
}
