<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

final class BrandingSection
{
    public static function make(): Section
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
}
