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
                    ->label('Logo')
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(3072)
                    ->helperText('Upload SVG, PNG, JPEG or WebP (max 3MB).'),
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

    public static function authentication(): Section
    {
        return Section::make('Authentication')
            ->description('Configure how users authenticate.')
            ->icon('heroicon-o-lock-closed')
            ->schema([
                Radio::make('auth_mode')->label('Authentication Mode')->options([
                    'local' => 'Local (email & password)',
                    'oauth' => 'OAuth (SSO)',
                ])->default('local')->live(),
                TextInput::make('oauth_client_id')->label('OAuth Client ID')->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
                TextInput::make('oauth_client_secret')->label('OAuth Client Secret')->password()->revealable()->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
                TextInput::make('oauth_redirect_url')->label('OAuth Redirect URL')->url()->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
            ])->columns(1);
    }

    public static function bridge(): Section
    {
        return Section::make('Bridge')
            ->description('Configure the bridge between Pelican and Stripe.')
            ->icon('heroicon-o-link')
            ->schema([
                Toggle::make('bridge_enabled')->label('Enable Bridge')->live(),
                TextInput::make('stripe_webhook_secret')->label('Stripe Webhook Secret')->password()->revealable()->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('bridge_enabled')),
            ])->columns(1);
    }
}
