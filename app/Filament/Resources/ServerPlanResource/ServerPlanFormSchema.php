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
                Tab::make(__('admin.tabs.shop_metadata'))
                    ->icon('heroicon-o-shopping-bag')
                    ->badge(__('admin.common.shop_managed'))
                    ->schema(self::shopMirrorFields())
                    ->columns(2),
                Tab::make(__('admin.tabs.peregrine_config'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema(self::peregrineConfigFields()),
            ])
            ->columnSpanFull();
    }

    /** @return array<int, mixed> */
    private static function shopMirrorFields(): array
    {
        return [
            TextInput::make('name')->label(__('admin.fields.name'))->disabled(),
            TextInput::make('shop_plan_slug')->label(__('admin.plans.fields.shop_slug'))->disabled(),
            TextInput::make('shop_plan_type')->label(__('admin.plans.fields.type'))->disabled(),
            Toggle::make('is_active')->label(__('admin.plans.fields.active_shop'))->disabled()->inline(false),
            TextInput::make('description')->label(__('admin.fields.description'))->disabled()->columnSpanFull(),

            TextInput::make('price_cents')
                ->label(__('admin.fields.price'))
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? '—' : number_format($state / 100, 2).' '.($record->currency ?? '')
                ),
            TextInput::make('interval')
                ->label(__('admin.plans.fields.recurrence'))
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null
                        ? __('admin.plans.fields.one_time')
                        : __('admin.plans.fields.every', ['count' => $record->interval_count, 'unit' => $state])
                ),
            TextInput::make('stripe_price_id')
                ->label(__('admin.fields.stripe_price'))
                ->disabled()
                ->placeholder(__('admin.plans.placeholders.stripe_not_synced')),
            TextInput::make('last_shop_synced_at')
                ->label(__('admin.plans.fields.last_synced'))
                ->disabled()
                ->formatStateUsing(fn ($state) => $state
                    ? \Illuminate\Support\Carbon::parse((string) $state)->locale(app()->getLocale())->diffForHumans()
                    : '—'),

            TextInput::make('ram')->label(__('admin.plans.fields.ram'))->disabled()->suffix('MB'),
            TextInput::make('cpu')->label(__('admin.plans.fields.cpu'))->disabled()->suffix('%'),
            TextInput::make('disk')->label(__('admin.fields.disk'))->disabled()->suffix('MB'),
            TextInput::make('swap_mb')->label(__('admin.plans.fields.swap'))->disabled()->suffix('MB'),
            TextInput::make('io_weight')->label(__('admin.plans.fields.io_weight'))->disabled(),
            TextInput::make('cpu_pinning')->label(__('admin.plans.fields.cpu_pinning'))->disabled()->placeholder('—'),
        ];
    }

    /** @return array<int, mixed> */
    private static function peregrineConfigFields(): array
    {
        return [
            Select::make('egg_id')
                ->label(__('admin.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin.plans.helpers.egg')),

            Toggle::make('auto_deploy')
                ->label(__('admin.plans.fields.auto_deploy'))
                ->helperText(__('admin.plans.helpers.auto_deploy'))
                ->live(),

            Select::make('default_node_id')
                ->label(__('admin.plans.fields.default_node'))
                ->relationship('defaultNode', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => ! $get('auto_deploy'))
                ->required(fn (Get $get) => ! $get('auto_deploy')),

            Select::make('allowed_node_ids')
                ->label(__('admin.plans.fields.allowed_nodes'))
                ->multiple()
                ->options(fn () => \App\Models\Node::pluck('name', 'id')->toArray())
                ->searchable()
                ->visible(fn (Get $get) => (bool) $get('auto_deploy'))
                ->required(fn (Get $get) => (bool) $get('auto_deploy'))
                ->helperText(__('admin.plans.helpers.allowed_nodes')),

            TextInput::make('docker_image')
                ->label(__('admin.plans.fields.docker_override'))
                ->maxLength(255)
                ->placeholder(__('admin.plans.placeholders.docker_default')),

            TextInput::make('port_count')
                ->label(__('admin.plans.fields.port_count'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(1)
                ->required()
                ->live()
                ->helperText(__('admin.plans.helpers.port_count')),

            Repeater::make('env_var_mapping')
                ->label(__('admin.plans.fields.env_mapping'))
                ->helperText(__('admin.plans.helpers.env_mapping'))
                ->schema([
                    TextInput::make('variable_name')
                        ->label(__('admin.plans.fields.variable_name'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(100),
                    Select::make('type')
                        ->label(__('admin.plans.fields.mapping_type'))
                        ->options([
                            'offset' => __('admin.plans.mapping_types.offset'),
                            'random' => __('admin.plans.mapping_types.random'),
                            'static' => __('admin.plans.mapping_types.static'),
                        ])
                        ->required()
                        ->live(),
                    Select::make('offset_value')
                        ->label(__('admin.plans.fields.which_port'))
                        ->options(function (Get $get): array {
                            $count = (int) ($get('../../port_count') ?? 1);
                            $count = max(1, min(10, $count));
                            $options = [];
                            for ($i = 0; $i < $count; $i++) {
                                $options[$i] = $i === 0
                                    ? __('admin.plans.fields.main_port')
                                    : __('admin.plans.fields.main_port_plus', ['n' => $i]);
                            }
                            return $options;
                        })
                        ->default(0)
                        ->visible(fn (Get $get) => $get('type') === 'offset')
                        ->required(fn (Get $get) => $get('type') === 'offset'),
                    TextInput::make('static_value')
                        ->label(__('admin.plans.fields.static_value'))
                        ->visible(fn (Get $get) => $get('type') === 'static')
                        ->required(fn (Get $get) => $get('type') === 'static'),
                ])
                ->collapsible()
                ->collapsed()
                ->reorderable(true)
                ->defaultItems(0),

            Toggle::make('enable_oom_killer')
                ->label(__('admin.plans.fields.enable_oom'))
                ->helperText(__('admin.plans.helpers.enable_oom')),
            Toggle::make('start_on_completion')
                ->label(__('admin.plans.fields.start_on_install'))
                ->helperText(__('admin.plans.helpers.start_on_install')),
            Toggle::make('skip_install_script')
                ->label(__('admin.plans.fields.skip_install'))
                ->helperText(__('admin.plans.helpers.skip_install')),
            Toggle::make('dedicated_ip')
                ->label(__('admin.plans.fields.dedicated_ip'))
                ->helperText(__('admin.plans.helpers.dedicated_ip')),

            Section::make(__('admin.plans.fields.feature_limits'))
                ->columns(3)
                ->schema([
                    TextInput::make('feature_limits_databases')->label(__('admin.plans.fields.databases'))->numeric()->default(0),
                    TextInput::make('feature_limits_backups')->label(__('admin.plans.fields.backups'))->numeric()->default(3),
                    TextInput::make('feature_limits_allocations')->label(__('admin.plans.fields.allocations'))->numeric()->default(1),
                ]),
        ];
    }
}
