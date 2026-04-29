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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;

final class ThemeFormSchema
{
    public static function tabs(): Tabs
    {
        return Tabs::make('theme-tabs')
            ->tabs([
                Tab::make(__('admin.tabs.colors'))
                    ->icon('heroicon-o-swatch')
                    ->schema(self::colorSections()),
                Tab::make(__('admin.tabs.cards'))
                    ->icon('heroicon-o-rectangle-group')
                    ->schema([self::cardSection()]),
                Tab::make(__('admin.tabs.sidebar'))
                    ->icon('heroicon-o-bars-3-bottom-left')
                    ->schema([self::sidebarSection()]),
            ])
            ->columnSpanFull();
    }


    /** @return array<Section> */
    public static function colorSections(): array
    {
        return [
            Section::make(__('admin.theme_form.presets.section'))
                ->description(__('admin.theme_form.presets.description'))
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Select::make('theme_preset')
                        ->label(__('admin.theme_form.presets.theme_preset'))
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
                    Select::make('theme_mode')->label(__('admin.theme_form.presets.color_mode'))->options([
                        'dark' => __('admin.theme_form.presets.mode_dark'),
                        'light' => __('admin.theme_form.presets.mode_light'),
                        'auto' => __('admin.theme_form.presets.mode_auto'),
                    ])->default('dark'),
                ])->columns(2),

            Section::make(__('admin.theme_form.brand_colors.section'))
                ->icon('heroicon-o-swatch')
                ->collapsible()
                ->schema([
                    ColorPicker::make('theme_primary')->label(__('admin.theme_form.brand_colors.primary')),
                    ColorPicker::make('theme_primary_hover')->label(__('admin.theme_form.brand_colors.primary_hover')),
                    ColorPicker::make('theme_secondary')->label(__('admin.theme_form.brand_colors.secondary')),
                    ColorPicker::make('theme_ring')->label(__('admin.theme_form.brand_colors.ring')),
                    ColorPicker::make('theme_danger')->label(__('admin.theme_form.brand_colors.danger')),
                    ColorPicker::make('theme_warning')->label(__('admin.theme_form.brand_colors.warning')),
                    ColorPicker::make('theme_success')->label(__('admin.theme_form.brand_colors.success')),
                    ColorPicker::make('theme_info')->label(__('admin.theme_form.brand_colors.info')),
                    ColorPicker::make('theme_suspended')
                        ->label(__('admin.theme_form.brand_colors.suspended'))
                        ->default('#f59e0b')
                        ->helperText(__('admin.theme_form.brand_colors.suspended_helper')),
                    ColorPicker::make('theme_installing')
                        ->label(__('admin.theme_form.brand_colors.installing'))
                        ->default('#3b82f6')
                        ->helperText(__('admin.theme_form.brand_colors.installing_helper')),
                ])->columns(4),

            Section::make(__('admin.theme_form.background.section'))
                ->icon('heroicon-o-square-3-stack-3d')
                ->collapsible()
                ->schema([
                    ColorPicker::make('theme_background')->label(__('admin.theme_form.background.background')),
                    ColorPicker::make('theme_surface')->label(__('admin.theme_form.background.surface')),
                    ColorPicker::make('theme_surface_hover')->label(__('admin.theme_form.background.surface_hover')),
                    ColorPicker::make('theme_surface_elevated')->label(__('admin.theme_form.background.surface_elevated')),
                ])->columns(2),

            Section::make(__('admin.theme_form.borders.section'))
                ->icon('heroicon-o-language')
                ->collapsible()
                ->schema([
                    TextInput::make('theme_border')->label(__('admin.theme_form.borders.border')),
                    TextInput::make('theme_border_hover')->label(__('admin.theme_form.borders.border_hover')),
                    ColorPicker::make('theme_text_primary')->label(__('admin.theme_form.borders.text_primary')),
                    ColorPicker::make('theme_text_secondary')->label(__('admin.theme_form.borders.text_secondary')),
                    ColorPicker::make('theme_text_muted')->label(__('admin.theme_form.borders.text_muted')),
                ])->columns(3),

            Section::make(__('admin.theme_form.typography.section'))
                ->icon('heroicon-o-pencil-square')
                ->collapsible()
                ->schema([
                    Select::make('theme_font')->label(__('admin.theme_form.typography.font_family'))->options([
                        'Inter' => 'Inter',
                        'Plus Jakarta Sans' => 'Plus Jakarta Sans',
                        'Space Grotesk' => 'Space Grotesk',
                        'Outfit' => 'Outfit',
                        'Manrope' => 'Manrope',
                        'Lexend' => 'Lexend',
                        'DM Sans' => 'DM Sans',
                        'Figtree' => 'Figtree',
                        'system-ui' => __('admin.theme_form.typography.font_system'),
                    ]),
                    Select::make('theme_radius')->label(__('admin.theme_form.typography.border_radius'))->options([
                        '0' => __('admin.theme_form.typography.radius_none'),
                        '0.25rem' => __('admin.theme_form.typography.radius_small'),
                        '0.375rem' => __('admin.theme_form.typography.radius_medium'),
                        '0.75rem' => __('admin.theme_form.typography.radius_large'),
                        '1rem' => __('admin.theme_form.typography.radius_xl'),
                        '1.5rem' => __('admin.theme_form.typography.radius_xxl'),
                    ]),
                    Select::make('theme_density')->label(__('admin.theme_form.typography.density'))->options([
                        'compact' => __('admin.theme_form.typography.density_compact'),
                        'comfortable' => __('admin.theme_form.typography.density_comfortable'),
                        'spacious' => __('admin.theme_form.typography.density_spacious'),
                    ])->helperText(__('admin.theme_form.typography.density_helper')),
                    TextInput::make('theme_shadow_intensity')
                        ->label(__('admin.theme_form.typography.shadow_intensity'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->helperText(__('admin.theme_form.typography.shadow_helper')),
                ])->columns(2),

            Section::make(__('admin.theme_form.custom_css.section'))
                ->icon('heroicon-o-code-bracket')
                ->collapsible()->collapsed()
                ->schema([
                    Textarea::make('theme_custom_css')->label('')->rows(6)->placeholder(__('admin.theme_form.custom_css.placeholder')),
                ]),
        ];
    }

    public static function cardSection(): Section
    {
        return Section::make(__('admin.theme_form.cards.section'))
            ->description(__('admin.theme_form.cards.description'))
            ->icon('heroicon-o-rectangle-group')
            ->collapsible()
            ->schema([
                Toggle::make('show_egg_icon')->label(__('admin.theme_form.cards.show_egg_icon')),
                Toggle::make('show_egg_name')->label(__('admin.theme_form.cards.show_egg_name')),
                Toggle::make('show_plan_name')->label(__('admin.theme_form.cards.show_plan_name')),
                Toggle::make('show_status_badge')->label(__('admin.theme_form.cards.show_status_badge')),
                Toggle::make('show_stats_bars')->label(__('admin.theme_form.cards.show_stats_bars')),
                Toggle::make('show_quick_actions')->label(__('admin.theme_form.cards.show_quick_actions')),
                Toggle::make('show_ip_port')->label(__('admin.theme_form.cards.show_ip_port')),
                Toggle::make('show_uptime')->label(__('admin.theme_form.cards.show_uptime')),
                Select::make('card_style')->label(__('admin.theme_form.cards.card_style'))->options([
                    'default' => __('admin.theme_form.cards.style_default'),
                    'elevated' => __('admin.theme_form.cards.style_elevated'),
                    'glass' => __('admin.theme_form.cards.style_glass'),
                    'minimal' => __('admin.theme_form.cards.style_minimal'),
                ]),
                Select::make('sort_default')->label(__('admin.theme_form.cards.sort_default'))->options([
                    'name' => __('admin.theme_form.cards.sort_name'),
                    'status' => __('admin.theme_form.cards.sort_status'),
                    'created_at' => __('admin.theme_form.cards.sort_created'),
                    'egg' => __('admin.theme_form.cards.sort_egg'),
                ]),
                Select::make('group_by')->label(__('admin.theme_form.cards.group_by'))->options([
                    'none' => __('admin.theme_form.cards.group_none'),
                    'egg' => __('admin.theme_form.cards.group_egg'),
                    'status' => __('admin.theme_form.cards.group_status'),
                    'plan' => __('admin.theme_form.cards.group_plan'),
                ]),
                Select::make('columns_desktop')->label(__('admin.theme_form.cards.cols_desktop'))->options([1 => '1', 2 => '2', 3 => '3', 4 => '4']),
                Select::make('columns_tablet')->label(__('admin.theme_form.cards.cols_tablet'))->options([1 => '1', 2 => '2', 3 => '3']),
                Select::make('columns_mobile')->label(__('admin.theme_form.cards.cols_mobile'))->options([1 => '1', 2 => '2']),
            ])->columns(2);
    }

    public static function sidebarSection(): Section
    {
        return Section::make(__('admin.theme_form.sidebar.section'))
            ->description(__('admin.theme_form.sidebar.description'))
            ->icon('heroicon-o-bars-3-bottom-left')
            ->collapsible()
            ->schema([
                Select::make('sidebar_preset')
                    ->label(__('admin.theme_form.sidebar.preset'))
                    ->helperText(__('admin.theme_form.sidebar.preset_helper'))
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
                Select::make('sidebar_position')->label(__('admin.theme_form.sidebar.position'))->options([
                    'left' => __('admin.theme_form.sidebar.position_left'),
                    'top' => __('admin.theme_form.sidebar.position_top'),
                    'dock' => __('admin.theme_form.sidebar.position_dock'),
                ]),
                Select::make('sidebar_style')->label(__('admin.theme_form.sidebar.style'))->options([
                    'default' => __('admin.theme_form.sidebar.style_default'),
                    'compact' => __('admin.theme_form.sidebar.style_compact'),
                    'pills' => __('admin.theme_form.sidebar.style_pills'),
                ]),
                Toggle::make('show_server_status')->label(__('admin.theme_form.sidebar.show_status')),
                Toggle::make('show_server_name')->label(__('admin.theme_form.sidebar.show_name')),
                Repeater::make('entries')->label(__('admin.theme_form.sidebar.links'))->schema([
                    TextInput::make('id')->label(__('admin.theme_form.sidebar.link_id'))->disabled()->dehydrated(),
                    TextInput::make('label_key')->label(__('admin.theme_form.sidebar.link_label_key')),
                    Select::make('icon')->label(__('admin.theme_form.sidebar.link_icon'))->options([
                        'home' => __('admin.theme_form.sidebar.icons.home'),
                        'terminal' => __('admin.theme_form.sidebar.icons.terminal'),
                        'folder' => __('admin.theme_form.sidebar.icons.folder'),
                        'database' => __('admin.theme_form.sidebar.icons.database'),
                        'archive' => __('admin.theme_form.sidebar.icons.archive'),
                        'clock' => __('admin.theme_form.sidebar.icons.clock'),
                        'globe' => __('admin.theme_form.sidebar.icons.globe'),
                        'key' => __('admin.theme_form.sidebar.icons.key'),
                        'settings' => __('admin.theme_form.sidebar.icons.settings'),
                        'shield' => __('admin.theme_form.sidebar.icons.shield'),
                        'users' => __('admin.theme_form.sidebar.icons.users'),
                        'server' => __('admin.theme_form.sidebar.icons.server'),
                        'link' => __('admin.theme_form.sidebar.icons.link'),
                        'code' => __('admin.theme_form.sidebar.icons.code'),
                        'cpu' => __('admin.theme_form.sidebar.icons.cpu'),
                        'hard-drive' => __('admin.theme_form.sidebar.icons.hard_drive'),
                    ]),
                    TextInput::make('route_suffix')->label(__('admin.theme_form.sidebar.link_route')),
                    Toggle::make('enabled')->label(__('admin.theme_form.sidebar.link_on')),
                ])->columns(5)->reorderable()->addable(false)->deletable(false)->columnSpanFull(),
            ])->columns(2);
    }
}
