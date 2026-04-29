<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class GeneralSection
{
    /**
     * @param  array<string, string>  $timezoneOptions
     */
    public static function make(array $timezoneOptions): Section
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
                    ->options(['en' => 'English', 'fr' => 'Français'])
                    ->default('en')
                    ->required()
                    ->helperText('Used for newly registered users (until they pick their own) and for the SPA when no language is detected from the browser.'),
                Select::make('app_timezone')
                    ->label('Application timezone')
                    ->options($timezoneOptions)
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
}
