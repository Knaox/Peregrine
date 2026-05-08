<?php

namespace App\Filament\Resources\ServerPlanResource;

use App\Models\ServerPlan;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

final class ServerPlanFormSchema
{
    public static function tabs(): Tabs
    {
        return Tabs::make('plan-tabs')
            ->tabs([
                Tab::make(__('admin/_shell.tabs.shop_metadata'))
                    ->icon('heroicon-o-shopping-bag')
                    ->badge(__('admin/_shell.common.shop_managed'))
                    ->schema(self::shopMirrorFields())
                    ->columns(2),
                Tab::make(__('admin/_shell.tabs.peregrine_config'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema(self::peregrineConfigFields()),
            ])
            ->columnSpanFull();
    }

    /** @return array<int, mixed> */
    private static function shopMirrorFields(): array
    {
        return [
            TextInput::make('name')->label(__('admin/_shell.fields.name'))->disabled(),
            TextInput::make('shop_plan_slug')->label(__('admin/server_plans.fields.shop_slug'))->disabled(),
            TextInput::make('shop_plan_type')->label(__('admin/server_plans.fields.type'))->disabled(),
            Toggle::make('is_active')->label(__('admin/server_plans.fields.active_shop'))->disabled()->inline(false),
            TextInput::make('description')->label(__('admin/_shell.fields.description'))->disabled()->columnSpanFull(),

            TextInput::make('price_cents')
                ->label(__('admin/_shell.fields.price'))
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? '—' : number_format($state / 100, 2).' '.($record->currency ?? '')
                ),
            TextInput::make('interval')
                ->label(__('admin/server_plans.fields.recurrence'))
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null
                        ? __('admin/server_plans.fields.one_time')
                        : __('admin/server_plans.fields.every', ['count' => $record->interval_count, 'unit' => $state])
                ),
            TextInput::make('stripe_price_id')
                ->label(__('admin/_shell.fields.stripe_price'))
                ->disabled()
                ->placeholder(__('admin/server_plans.placeholders.stripe_not_synced')),
            TextInput::make('last_shop_synced_at')
                ->label(__('admin/server_plans.fields.last_synced'))
                ->disabled()
                ->formatStateUsing(fn ($state) => $state
                    ? \Illuminate\Support\Carbon::parse((string) $state)->locale(app()->getLocale())->diffForHumans()
                    : '—'),

            TextInput::make('ram')->label(__('admin/server_plans.fields.ram'))->disabled()->suffix('MB'),
            TextInput::make('cpu')->label(__('admin/server_plans.fields.cpu'))->disabled()->suffix('%'),
            TextInput::make('disk')->label(__('admin/_shell.fields.disk'))->disabled()->suffix('MB'),
            TextInput::make('swap_mb')->label(__('admin/server_plans.fields.swap'))->disabled()->suffix('MB'),
            TextInput::make('io_weight')->label(__('admin/server_plans.fields.io_weight'))->disabled(),
            TextInput::make('cpu_pinning')->label(__('admin/server_plans.fields.cpu_pinning'))->disabled()->placeholder('—'),
        ];
    }

    /** @return array<int, mixed> */
    private static function peregrineConfigFields(): array
    {
        return [
            Select::make('egg_id')
                ->label(__('admin/_shell.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin/server_plans.helpers.egg')),

            Toggle::make('auto_deploy')
                ->label(__('admin/server_plans.fields.auto_deploy'))
                ->helperText(__('admin/server_plans.helpers.auto_deploy'))
                ->live(),

            Select::make('default_node_id')
                ->label(__('admin/server_plans.fields.default_node'))
                ->relationship('defaultNode', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => ! $get('auto_deploy'))
                ->required(fn (Get $get) => ! $get('auto_deploy')),

            Select::make('allowed_node_ids')
                ->label(__('admin/server_plans.fields.allowed_nodes'))
                ->multiple()
                ->options(fn () => \App\Models\Node::pluck('name', 'id')->toArray())
                ->searchable()
                ->visible(fn (Get $get) => (bool) $get('auto_deploy'))
                ->required(fn (Get $get) => (bool) $get('auto_deploy'))
                ->helperText(__('admin/server_plans.helpers.allowed_nodes')),

            TextInput::make('docker_image')
                ->label(__('admin/server_plans.fields.docker_override'))
                ->maxLength(255)
                ->placeholder(__('admin/server_plans.placeholders.docker_default')),

            TextInput::make('port_count')
                ->label(__('admin/server_plans.fields.port_count'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(1)
                ->required()
                ->live()
                ->helperText(__('admin/server_plans.helpers.port_count')),

            Repeater::make('env_var_mapping')
                ->label(__('admin/server_plans.fields.env_mapping'))
                ->helperText(__('admin/server_plans.helpers.env_mapping'))
                ->schema([
                    TextInput::make('variable_name')
                        ->label(__('admin/server_plans.fields.variable_name'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(100),
                    Select::make('type')
                        ->label(__('admin/server_plans.fields.mapping_type'))
                        ->options([
                            'offset' => __('admin/server_plans.mapping_types.offset'),
                            'random' => __('admin/server_plans.mapping_types.random'),
                            'static' => __('admin/server_plans.mapping_types.static'),
                        ])
                        ->required()
                        ->live(),
                    Select::make('offset_value')
                        ->label(__('admin/server_plans.fields.which_port'))
                        ->options(function (Get $get): array {
                            $count = (int) ($get('../../port_count') ?? 1);
                            $count = max(1, min(10, $count));
                            $options = [];
                            for ($i = 0; $i < $count; $i++) {
                                $options[$i] = $i === 0
                                    ? __('admin/server_plans.fields.main_port')
                                    : __('admin/server_plans.fields.main_port_plus', ['n' => $i]);
                            }
                            return $options;
                        })
                        ->default(0)
                        ->visible(fn (Get $get) => $get('type') === 'offset')
                        ->required(fn (Get $get) => $get('type') === 'offset'),
                    TextInput::make('static_value')
                        ->label(__('admin/server_plans.fields.static_value'))
                        ->visible(fn (Get $get) => $get('type') === 'static')
                        ->required(fn (Get $get) => $get('type') === 'static'),
                ])
                ->collapsible()
                ->collapsed()
                ->reorderable(true)
                ->defaultItems(0),

            Toggle::make('enable_oom_killer')
                ->label(__('admin/server_plans.fields.enable_oom'))
                ->helperText(__('admin/server_plans.helpers.enable_oom')),
            Toggle::make('start_on_completion')
                ->label(__('admin/server_plans.fields.start_on_install'))
                ->helperText(__('admin/server_plans.helpers.start_on_install')),
            Toggle::make('skip_install_script')
                ->label(__('admin/server_plans.fields.skip_install'))
                ->helperText(__('admin/server_plans.helpers.skip_install')),
            Toggle::make('dedicated_ip')
                ->label(__('admin/server_plans.fields.dedicated_ip'))
                ->helperText(__('admin/server_plans.helpers.dedicated_ip')),

            Section::make(__('admin/server_plans.fields.feature_limits'))
                ->columns(3)
                ->schema([
                    TextInput::make('feature_limits_databases')->label(__('admin/server_plans.fields.databases'))->numeric()->default(0),
                    TextInput::make('feature_limits_backups')->label(__('admin/server_plans.fields.backups'))->numeric()->default(3),
                    TextInput::make('feature_limits_allocations')->label(__('admin/server_plans.fields.allocations'))->numeric()->default(1),
                ]),
        ];
    }
}
