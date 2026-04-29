<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

final class BrandingSection
{
    public static function make(): Section
    {
        return Section::make(__('admin.settings_form.branding.section'))
            ->description(__('admin.settings_form.branding.description'))
            ->icon('heroicon-o-photo')
            ->schema([
                Select::make('logo_height')
                    ->label(__('admin.settings_form.branding.logo_size'))
                    ->options([
                        '24' => __('admin.settings_form.branding.logo_size_options.small'),
                        '32' => __('admin.settings_form.branding.logo_size_options.medium'),
                        '40' => __('admin.settings_form.branding.logo_size_options.large'),
                        '48' => __('admin.settings_form.branding.logo_size_options.xl'),
                        '56' => __('admin.settings_form.branding.logo_size_options.xxl'),
                    ])
                    ->default('40'),
                FileUpload::make('logo_url')
                    ->label(__('admin.settings_form.branding.logo_dark'))
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(3072)
                    ->helperText(__('admin.settings_form.branding.logo_dark_helper')),
                FileUpload::make('logo_url_light')
                    ->label(__('admin.settings_form.branding.logo_light'))
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(3072)
                    ->helperText(__('admin.settings_form.branding.logo_light_helper')),
                FileUpload::make('favicon_url')
                    ->label(__('admin.settings_form.branding.favicon'))
                    ->image()
                    ->directory('branding')
                    ->disk('public')
                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                    ->maxSize(1024)
                    ->helperText(__('admin.settings_form.branding.favicon_helper')),
            ])->columns(1);
    }
}
