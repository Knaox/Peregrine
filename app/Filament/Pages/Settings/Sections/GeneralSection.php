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
        return Section::make(__('admin.settings_form.general.section'))
            ->description(__('admin.settings_form.general.description'))
            ->icon('heroicon-o-identification')
            ->schema([
                TextInput::make('app_name')
                    ->label(__('admin.settings_form.general.app_name'))
                    ->placeholder('Peregrine')
                    ->maxLength(255),
                Toggle::make('show_app_name')
                    ->label(__('admin.settings_form.general.show_app_name'))
                    ->helperText(__('admin.settings_form.general.show_app_name_helper')),
                Select::make('default_locale')
                    ->label(__('admin.settings_form.general.default_language'))
                    ->options(['en' => 'English', 'fr' => 'Français'])
                    ->default('en')
                    ->required()
                    ->helperText(__('admin.settings_form.general.default_language_helper')),
                Select::make('app_timezone')
                    ->label(__('admin.settings_form.general.timezone'))
                    ->options($timezoneOptions)
                    ->default('UTC')
                    ->required()
                    ->searchable()
                    ->helperText(__('admin.settings_form.general.timezone_helper')),
                Repeater::make('header_links')
                    ->label(__('admin.settings_form.general.header_links'))
                    ->helperText(__('admin.settings_form.general.header_links_helper'))
                    ->schema([
                        TextInput::make('label')->label(__('admin.settings_form.general.label_en'))->required()->placeholder('Shop'),
                        TextInput::make('label_fr')->label(__('admin.settings_form.general.label_fr'))->placeholder('Boutique'),
                        TextInput::make('url')->label(__('admin.settings_form.general.url'))->required()->placeholder('https://example.com'),
                        Select::make('icon')->label(__('admin.settings_form.general.icon'))->options([
                            'none' => __('admin.settings_form.general.icons.none'),
                            'home' => __('admin.settings_form.general.icons.home'),
                            'shopping-bag' => __('admin.settings_form.general.icons.shop'),
                            'ticket' => __('admin.settings_form.general.icons.ticket'),
                            'user' => __('admin.settings_form.general.icons.user'),
                            'cog' => __('admin.settings_form.general.icons.settings'),
                            'chat' => __('admin.settings_form.general.icons.discord'),
                            'book' => __('admin.settings_form.general.icons.docs'),
                            'globe' => __('admin.settings_form.general.icons.website'),
                            'server' => __('admin.settings_form.general.icons.server'),
                            'shield' => __('admin.settings_form.general.icons.security'),
                            'heart' => __('admin.settings_form.general.icons.donate'),
                            'star' => __('admin.settings_form.general.icons.premium'),
                            'link' => __('admin.settings_form.general.icons.link'),
                        ])->default('none'),
                        Toggle::make('new_tab')->label(__('admin.settings_form.general.new_tab'))->default(true),
                    ])->columns(5)->reorderable()->defaultItems(0),
            ])->columns(1);
    }
}
