<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Settings page form schema, organised in top-level tabs so each concern
 * (identity, visuals, infra, mail, network, advanced) gets its own page-like
 * surface instead of one long scroll. Pattern inspired by Paymenter / Pelican
 * admin UX — every panel lives behind a single icon-prefixed tab.
 */
final class SettingsFormSchema
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function tabs(): array
    {
        return [
            Tabs::make('settings_tabs')
                ->tabs([
                    Tab::make('General')
                        ->icon('heroicon-o-home')
                        ->schema([self::general()]),
                    Tab::make('Branding')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([self::branding()]),
                    Tab::make('Pelican')
                        ->icon('heroicon-o-globe-alt')
                        ->schema([self::pelican()]),
                    Tab::make('Mail')
                        ->icon('heroicon-o-envelope')
                        ->schema([self::smtp()]),
                    Tab::make('Network')
                        ->icon('heroicon-o-shield-check')
                        ->schema([self::network()]),
                    Tab::make('Advanced')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema([self::developer()]),
                ])
                ->persistTabInQueryString('settingsTab'),
        ];
    }

    public static function general(): Section
    {
        return Section::make('Identity')
            ->description('Application name, default language, and top-bar navigation links.')
            ->icon('heroicon-o-identification')
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
                Select::make('app_timezone')
                    ->label('Application timezone')
                    ->options(self::timezoneOptions())
                    ->default('UTC')
                    ->required()
                    ->searchable()
                    ->helperText('Used by Carbon::now(), scheduled jobs, sync logs, and email timestamps. Changes apply on the next request — no restart required. Stored in DB so a Docker stack rebuild does not reset it.'),
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

    public static function branding(): Section
    {
        return Section::make('Logo & Favicon')
            ->description('Visual identity — uploaded files are served from /storage/branding.')
            ->icon('heroicon-o-photo')
            ->schema([
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

    public static function network(): Section
    {
        return Section::make('Trusted Proxies')
            ->description(
                'IPs or CIDR ranges allowed to set X-Forwarded-* headers. Set this when '
                . 'Peregrine sits behind a reverse proxy (Nginx Proxy Manager, Traefik, '
                . 'Cloudflare, …). Leave empty to trust no proxy. Stored in DB (table '
                . '`settings`) so a Docker stack rebuild does not reset it ; applies on '
                . 'the next request, no container restart needed.'
            )
            ->icon('heroicon-o-shield-check')
            ->headerActions([
                Action::make('clearTrustedProxies')
                    ->label('Clear')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->link()
                    ->action(fn (Set $set) => $set('trusted_proxies', [])),
                Action::make('setCloudflareIps')
                    ->label('Set to Cloudflare IPs')
                    ->icon('heroicon-o-cloud')
                    ->color('primary')
                    ->link()
                    ->action(function (Get $get, Set $set): void {
                        // Merge current entries (so the operator's private
                        // proxy IP is preserved) with Cloudflare's official
                        // ranges. Dedupe to keep the chip list tidy.
                        $current = $get('trusted_proxies') ?? [];
                        $merged = array_values(array_unique([
                            ...(is_array($current) ? $current : []),
                            ...CloudflareIps::all(),
                        ]));
                        $set('trusted_proxies', $merged);
                    }),
            ])
            ->schema([
                TagsInput::make('trusted_proxies')
                    ->label('')
                    ->placeholder('New IP or IP Range')
                    ->reorderable()
                    ->helperText(
                        'Examples: 192.168.80.1 (single host), 172.16.0.0/12 (CIDR range), '
                        . '2400:cb00::/32 (IPv6 CIDR). Tip: click "Set to Cloudflare IPs" '
                        . 'to seed the list with all of Cloudflare\'s edge IPs, then add '
                        . 'your own private proxy IP.'
                    ),
            ])->columns(1);
    }

    public static function developer(): Section
    {
        return Section::make('Developer')
            ->description('Low-level toggles. Leave debug mode OFF in production — when enabled, full PHP stack traces are exposed to clients on errors.')
            ->icon('heroicon-o-bug-ant')
            ->schema([
                Toggle::make('app_debug')
                    ->label('Enable debug mode (APP_DEBUG)')
                    ->helperText('Stored in DB (table `settings`) — survives a Docker stack rebuild. Applies on the next request, no container restart needed.'),
            ])->columns(1);
    }

    /**
     * IANA timezone list, with a few shortcuts pinned at the top so the
     * common picks are 1 click away.
     *
     * @return array<string, string>
     */
    private static function timezoneOptions(): array
    {
        $pinned = [
            'UTC' => 'UTC (Coordinated Universal Time)',
            'Europe/Paris' => 'Europe/Paris (CET/CEST)',
            'Europe/London' => 'Europe/London (GMT/BST)',
            'America/New_York' => 'America/New_York (EST/EDT)',
            'America/Los_Angeles' => 'America/Los_Angeles (PST/PDT)',
        ];
        $rest = [];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            if (! isset($pinned[$tz])) {
                $rest[$tz] = $tz;
            }
        }
        return $pinned + $rest;
    }
}
