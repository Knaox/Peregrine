<?php

namespace App\Filament\Pages\Theme;

use App\Support\SidebarPresets;
use App\Support\ThemePresets;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

final class ThemeFormSchema
{
    /** @return array<Section> */
    public static function colorSections(): array
    {
        return [
            Section::make('Presets')
                ->description('Apply a named bundle of colors, surfaces and accents in one click — then fine-tune below.')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Select::make('theme_preset')
                        ->label('Theme Preset')
                        ->options(ThemePresets::options())
                        ->default('orange')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (! $state || $state === 'custom') {
                                return;
                            }
                            foreach (ThemePresets::get($state) as $key => $value) {
                                $set($key, $value);
                            }
                        }),
                    Select::make('theme_mode')->label('Color Mode')->options([
                        'dark' => 'Dark', 'light' => 'Light', 'auto' => 'Auto (system)',
                    ])->default('dark'),
                ])->columns(2),

            Section::make('Brand Colors')
                ->icon('heroicon-o-swatch')
                ->collapsible()
                ->schema([
                    ColorPicker::make('theme_primary')->label('Primary'),
                    ColorPicker::make('theme_primary_hover')->label('Primary Hover'),
                    ColorPicker::make('theme_secondary')->label('Secondary / Accent'),
                    ColorPicker::make('theme_ring')->label('Focus Ring'),
                    ColorPicker::make('theme_danger')->label('Danger'),
                    ColorPicker::make('theme_warning')->label('Warning'),
                    ColorPicker::make('theme_success')->label('Success'),
                    ColorPicker::make('theme_info')->label('Info'),
                    ColorPicker::make('theme_suspended')
                        ->label('Suspended server accent')
                        ->default('#f59e0b')
                        ->helperText('Tiny pill + left border on server cards for suspended servers.'),
                    ColorPicker::make('theme_installing')
                        ->label('Installing server accent')
                        ->default('#3b82f6')
                        ->helperText('Tiny pill + left border on server cards during install.'),
                ])->columns(4),

            Section::make('Background & Surfaces')
                ->icon('heroicon-o-square-3-stack-3d')
                ->collapsible()
                ->schema([
                    ColorPicker::make('theme_background')->label('Background'),
                    ColorPicker::make('theme_surface')->label('Surface'),
                    ColorPicker::make('theme_surface_hover')->label('Surface Hover'),
                    ColorPicker::make('theme_surface_elevated')->label('Surface Elevated'),
                ])->columns(2),

            Section::make('Borders & Text')
                ->icon('heroicon-o-language')
                ->collapsible()
                ->schema([
                    TextInput::make('theme_border')->label('Border'),
                    TextInput::make('theme_border_hover')->label('Border Hover'),
                    ColorPicker::make('theme_text_primary')->label('Text Primary'),
                    ColorPicker::make('theme_text_secondary')->label('Text Secondary'),
                    ColorPicker::make('theme_text_muted')->label('Text Muted'),
                ])->columns(3),

            Section::make('Typography, Shape & Density')
                ->icon('heroicon-o-pencil-square')
                ->collapsible()
                ->schema([
                    Select::make('theme_font')->label('Font Family')->options([
                        'Inter' => 'Inter',
                        'Plus Jakarta Sans' => 'Plus Jakarta Sans',
                        'Space Grotesk' => 'Space Grotesk',
                        'Outfit' => 'Outfit',
                        'Manrope' => 'Manrope',
                        'Lexend' => 'Lexend',
                        'DM Sans' => 'DM Sans',
                        'Figtree' => 'Figtree',
                        'system-ui' => 'System Default',
                    ]),
                    Select::make('theme_radius')->label('Border Radius')->options([
                        '0' => 'None (sharp)', '0.25rem' => 'Small', '0.375rem' => 'Medium',
                        '0.75rem' => 'Large (default)', '1rem' => 'Extra Large', '1.5rem' => '2XL',
                    ]),
                    Select::make('theme_density')->label('Density')->options([
                        'compact' => 'Compact',
                        'comfortable' => 'Comfortable (default)',
                        'spacious' => 'Spacious',
                    ])->helperText('Controls padding and spacing across cards, buttons and inputs.'),
                    TextInput::make('theme_shadow_intensity')
                        ->label('Shadow Intensity')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->helperText('0 = flat, 100 = heavy shadows.'),
                ])->columns(2),

            Section::make('Custom CSS')
                ->icon('heroicon-o-code-bracket')
                ->collapsible()->collapsed()
                ->schema([
                    Textarea::make('theme_custom_css')->label('')->rows(6)->placeholder('/* Your custom CSS here */'),
                ]),
        ];
    }

    public static function cardSection(): Section
    {
        return Section::make('Server Cards')
            ->description('Configure what is displayed on each server card.')
            ->icon('heroicon-o-rectangle-group')
            ->collapsible()
            ->schema([
                Toggle::make('show_egg_icon')->label('Show egg icon/banner'),
                Toggle::make('show_egg_name')->label('Show egg name'),
                Toggle::make('show_plan_name')->label('Show plan name'),
                Toggle::make('show_status_badge')->label('Show status badge'),
                Toggle::make('show_stats_bars')->label('Show stats bars'),
                Toggle::make('show_quick_actions')->label('Show power actions'),
                Toggle::make('show_ip_port')->label('Show IP:port'),
                Toggle::make('show_uptime')->label('Show uptime'),
                Select::make('card_style')->label('Card Style')->options([
                    'default' => 'Default', 'elevated' => 'Elevated', 'glass' => 'Glass', 'minimal' => 'Minimal',
                ]),
                Select::make('sort_default')->label('Default Sort')->options([
                    'name' => 'Name', 'status' => 'Status', 'created_at' => 'Date Created', 'egg' => 'Egg Type',
                ]),
                Select::make('group_by')->label('Group By')->options([
                    'none' => 'No Grouping', 'egg' => 'Egg Type', 'status' => 'Status', 'plan' => 'Plan',
                ]),
                Select::make('columns_desktop')->label('Desktop Columns')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4']),
                Select::make('columns_tablet')->label('Tablet Columns')->options([1 => '1', 2 => '2', 3 => '3']),
                Select::make('columns_mobile')->label('Mobile Columns')->options([1 => '1', 2 => '2']),
            ])->columns(2);
    }

    public static function sidebarSection(): Section
    {
        return Section::make('Server Sidebar')
            ->description('Configure the sidebar on server detail pages.')
            ->icon('heroicon-o-bars-3-bottom-left')
            ->collapsible()
            ->schema([
                Select::make('sidebar_preset')
                    ->label('Sidebar Preset')
                    ->helperText('Pick a complete layout — position, style and header together. Choose Custom to fine-tune each field.')
                    ->options(SidebarPresets::options())
                    ->default('classic')
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (! $state || $state === 'custom') {
                            return;
                        }
                        foreach (SidebarPresets::get($state) as $key => $value) {
                            $target = match ($key) {
                                'position' => 'sidebar_position',
                                'style' => 'sidebar_style',
                                default => $key,
                            };
                            $set($target, $value);
                        }
                    })
                    ->columnSpanFull(),
                Select::make('sidebar_position')->label('Position')->options([
                    'left' => 'Left sidebar',
                    'top' => 'Top tabs',
                    'dock' => 'Bottom dock',
                ]),
                Select::make('sidebar_style')->label('Style')->options(['default' => 'Default', 'compact' => 'Compact (rail)', 'pills' => 'Pills']),
                Toggle::make('show_server_status')->label('Show status dot'),
                Toggle::make('show_server_name')->label('Show server name'),
                Repeater::make('entries')->label('Sidebar Links')->schema([
                    TextInput::make('id')->label('ID')->disabled()->dehydrated(),
                    TextInput::make('label_key')->label('i18n Key'),
                    Select::make('icon')->label('Icon')->options([
                        'home' => 'Home', 'terminal' => 'Terminal', 'folder' => 'Folder',
                        'database' => 'Database', 'archive' => 'Archive', 'clock' => 'Clock',
                        'globe' => 'Globe', 'key' => 'Key', 'settings' => 'Settings',
                        'shield' => 'Shield', 'users' => 'Users', 'server' => 'Server',
                        'link' => 'Link', 'code' => 'Code', 'cpu' => 'CPU', 'hard-drive' => 'Hard Drive',
                    ]),
                    TextInput::make('route_suffix')->label('Route'),
                    Toggle::make('enabled')->label('On'),
                ])->columns(5)->reorderable()->addable(false)->deletable(false)->columnSpanFull(),
            ])->columns(2);
    }
}
