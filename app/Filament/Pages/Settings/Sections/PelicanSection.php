<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

final class PelicanSection
{
    public static function make(): Section
    {
        return Section::make(__('admin.settings_form.pelican.section'))
            ->description(__('admin.settings_form.pelican.description'))
            ->icon('heroicon-o-globe-alt')
            ->schema([
                TextInput::make('pelican_url')->label(__('admin.settings_form.pelican.url'))->placeholder('https://panel.example.com')->url()->maxLength(255),
                TextInput::make('pelican_admin_api_key')->label(__('admin.settings_form.pelican.admin_key'))->password()->revealable()->maxLength(255)
                    ->helperText(__('admin.settings_form.pelican.admin_key_helper')),
                TextInput::make('pelican_client_api_key')->label(__('admin.settings_form.pelican.client_key'))->password()->revealable()->maxLength(255)
                    ->helperText(__('admin.settings_form.pelican.client_key_helper')),
            ])->columns(1);
    }
}
