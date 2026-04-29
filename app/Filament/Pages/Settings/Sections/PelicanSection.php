<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

final class PelicanSection
{
    public static function make(): Section
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
}
