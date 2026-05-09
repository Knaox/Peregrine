<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Form schema for ServerConfigurationResource. Three tabs :
 *  - Identity : internal name, technical description, server name template
 *  - Resources : RAM / CPU / disk / swap / IO weight / CPU pinning
 *  - Pelican config : egg, node selection, docker, port mapping, env vars,
 *    runtime toggles, feature limits
 *
 * Strictly technical — no commercial fields. The shop manages pricing,
 * marketing names, billing cycles in its own backend ; Peregrine only
 * exposes the configuration id via the public API for the shop to reference
 * in Stripe metadata.
 */
final class ServerConfigurationFormSchema
{
    public static function tabs(): Tabs
    {
        return Tabs::make('configuration-tabs')
            ->tabs([
                Tab::make(__('admin/server_configurations.tabs.identity'))
                    ->icon('heroicon-o-identification')
                    ->schema(self::identityFields())
                    ->columns(2),
                Tab::make(__('admin/server_configurations.tabs.resources'))
                    ->icon('heroicon-o-cpu-chip')
                    ->schema(self::resourceFields())
                    ->columns(2),
                Tab::make(__('admin/server_configurations.tabs.pelican_config'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema(self::peregrineConfigFields()),
            ])
            ->columnSpanFull();
    }

    /** @return array<int, mixed> */
    private static function identityFields(): array
    {
        return [
            TextInput::make('internal_name')
                ->label(__('admin/server_configurations.fields.internal_name'))
                ->helperText(__('admin/server_configurations.helpers.internal_name'))
                ->required()
                ->maxLength(255),

            TextInput::make('name_template')
                ->label(__('admin/server_configurations.fields.name_template'))
                ->helperText(__('admin/server_configurations.helpers.name_template'))
                ->required()
                ->default('{user.username}-{configuration.internal_name}')
                ->maxLength(255),

            Textarea::make('technical_description')
                ->label(__('admin/server_configurations.fields.technical_description'))
                ->helperText(__('admin/server_configurations.helpers.technical_description'))
                ->columnSpanFull()
                ->rows(3),
        ];
    }

    /** @return array<int, mixed> */
    private static function resourceFields(): array
    {
        return [
            TextInput::make('ram')
                ->label(__('admin/server_configurations.fields.ram'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->suffix('MB'),

            TextInput::make('cpu')
                ->label(__('admin/server_configurations.fields.cpu'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->suffix('%'),

            TextInput::make('disk')
                ->label(__('admin/server_configurations.fields.disk'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->suffix('MB'),

            TextInput::make('swap_mb')
                ->label(__('admin/server_configurations.fields.swap'))
                ->numeric()
                ->minValue(-1)
                ->default(0)
                ->suffix('MB'),

            TextInput::make('io_weight')
                ->label(__('admin/server_configurations.fields.io_weight'))
                ->numeric()
                ->minValue(10)
                ->maxValue(1000)
                ->default(500)
                ->helperText(__('admin/server_configurations.helpers.io_weight')),

            TextInput::make('cpu_pinning')
                ->label(__('admin/server_configurations.fields.cpu_pinning'))
                ->placeholder('e.g. 0-3')
                ->helperText(__('admin/server_configurations.helpers.cpu_pinning'))
                ->maxLength(64),
        ];
    }

    /** @return array<int, mixed> */
    private static function peregrineConfigFields(): array
    {
        return [
            Select::make('egg_id')
                ->label(__('admin/server_configurations.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin/server_configurations.helpers.egg')),

            Toggle::make('auto_deploy')
                ->label(__('admin/server_configurations.fields.auto_deploy'))
                ->helperText(__('admin/server_configurations.helpers.auto_deploy'))
                ->live(),

            Select::make('default_node_id')
                ->label(__('admin/server_configurations.fields.default_node'))
                ->relationship('defaultNode', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => ! $get('auto_deploy'))
                ->required(fn (Get $get) => ! $get('auto_deploy')),

            Select::make('allowed_node_ids')
                ->label(__('admin/server_configurations.fields.allowed_nodes'))
                ->multiple()
                ->options(fn () => \App\Models\Node::pluck('name', 'id')->toArray())
                ->searchable()
                ->visible(fn (Get $get) => (bool) $get('auto_deploy'))
                ->required(fn (Get $get) => (bool) $get('auto_deploy'))
                ->helperText(__('admin/server_configurations.helpers.allowed_nodes')),

            TextInput::make('docker_image')
                ->label(__('admin/server_configurations.fields.docker_override'))
                ->maxLength(255)
                ->placeholder(__('admin/server_configurations.placeholders.docker_default')),

            TextInput::make('port_count')
                ->label(__('admin/server_configurations.fields.port_count'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(1)
                ->required()
                ->live()
                ->helperText(__('admin/server_configurations.helpers.port_count')),

            Repeater::make('env_var_mapping')
                ->label(__('admin/server_configurations.fields.env_mapping'))
                ->helperText(__('admin/server_configurations.helpers.env_mapping'))
                ->schema([
                    TextInput::make('variable_name')
                        ->label(__('admin/server_configurations.fields.variable_name'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(100),
                    Select::make('type')
                        ->label(__('admin/server_configurations.fields.mapping_type'))
                        ->options([
                            'offset' => __('admin/server_configurations.mapping_types.offset'),
                            'random' => __('admin/server_configurations.mapping_types.random'),
                            'static' => __('admin/server_configurations.mapping_types.static'),
                        ])
                        ->required()
                        ->live(),
                    Select::make('offset_value')
                        ->label(__('admin/server_configurations.fields.which_port'))
                        ->options(function (Get $get): array {
                            $count = (int) ($get('../../port_count') ?? 1);
                            $count = max(1, min(10, $count));
                            $options = [];
                            for ($i = 0; $i < $count; $i++) {
                                $options[$i] = $i === 0
                                    ? __('admin/server_configurations.fields.main_port')
                                    : __('admin/server_configurations.fields.main_port_plus', ['n' => $i]);
                            }
                            return $options;
                        })
                        ->default(0)
                        ->visible(fn (Get $get) => $get('type') === 'offset')
                        ->required(fn (Get $get) => $get('type') === 'offset'),
                    TextInput::make('static_value')
                        ->label(__('admin/server_configurations.fields.static_value'))
                        ->visible(fn (Get $get) => $get('type') === 'static')
                        ->required(fn (Get $get) => $get('type') === 'static'),
                ])
                ->collapsible()
                ->collapsed()
                ->reorderable(true)
                ->defaultItems(0),

            Toggle::make('enable_oom_killer')
                ->label(__('admin/server_configurations.fields.enable_oom'))
                ->helperText(__('admin/server_configurations.helpers.enable_oom'))
                ->default(true),
            Toggle::make('start_on_completion')
                ->label(__('admin/server_configurations.fields.start_on_install'))
                ->helperText(__('admin/server_configurations.helpers.start_on_install'))
                ->default(true),
            Toggle::make('skip_install_script')
                ->label(__('admin/server_configurations.fields.skip_install'))
                ->helperText(__('admin/server_configurations.helpers.skip_install')),
            Toggle::make('dedicated_ip')
                ->label(__('admin/server_configurations.fields.dedicated_ip'))
                ->helperText(__('admin/server_configurations.helpers.dedicated_ip')),

            Section::make(__('admin/server_configurations.fields.feature_limits'))
                ->columns(3)
                ->schema([
                    TextInput::make('feature_limits_databases')
                        ->label(__('admin/server_configurations.fields.databases'))
                        ->numeric()
                        ->default(0),
                    TextInput::make('feature_limits_backups')
                        ->label(__('admin/server_configurations.fields.backups'))
                        ->numeric()
                        ->default(3),
                    TextInput::make('feature_limits_allocations')
                        ->label(__('admin/server_configurations.fields.allocations'))
                        ->numeric()
                        ->default(1),
                ]),
        ];
    }
}
