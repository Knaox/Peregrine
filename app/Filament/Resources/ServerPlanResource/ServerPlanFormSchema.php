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

/**
 * Form sections for `ServerPlanResource` — surfaced as Tabs.
 *
 * UX rule : the form is split in two — Shop-owned fields read-only (price,
 * RAM/CPU/disk promised to the customer) + Peregrine-only technical config
 * (egg, node, env mapping, runtime toggles).
 */
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
            TextInput::make('name')->disabled(),
            TextInput::make('shop_plan_slug')->label('Shop slug')->disabled(),
            TextInput::make('shop_plan_type')->label('Type')->disabled(),
            Toggle::make('is_active')->label('Active (Shop)')->disabled()->inline(false),
            TextInput::make('description')->disabled()->columnSpanFull(),

            TextInput::make('price_cents')
                ->label('Price')
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? '—' : number_format($state / 100, 2).' '.($record->currency ?? '')
                ),
            TextInput::make('interval')
                ->label('Recurrence')
                ->disabled()
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? 'one-time' : 'every '.$record->interval_count.' '.$state
                ),
            TextInput::make('stripe_price_id')
                ->label('Stripe Price')
                ->disabled()
                ->placeholder('Not yet synced to Stripe (Shop side)'),
            TextInput::make('last_shop_synced_at')
                ->label('Last synced from Shop')
                ->disabled()
                ->formatStateUsing(fn ($state) => $state
                    ? \Illuminate\Support\Carbon::parse((string) $state)->diffForHumans()
                    : '—'),

            TextInput::make('ram')->label('RAM')->disabled()->suffix('MB'),
            TextInput::make('cpu')->label('CPU')->disabled()->suffix('%'),
            TextInput::make('disk')->label('Disk')->disabled()->suffix('MB'),
            TextInput::make('swap_mb')->label('Swap')->disabled()->suffix('MB'),
            TextInput::make('io_weight')->label('I/O weight')->disabled(),
            TextInput::make('cpu_pinning')->label('CPU pinning')->disabled()->placeholder('—'),
        ];
    }

    /** @return array<int, mixed> */
    private static function peregrineConfigFields(): array
    {
        return [
            Select::make('egg_id')
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('The Pelican egg used when provisioning this plan. The nest is derived automatically.'),

            Toggle::make('auto_deploy')
                ->label('Auto-deploy on multiple nodes')
                ->helperText('ON: provisioner picks one of the allowed nodes (least-loaded). OFF: every server lands on the default node.')
                ->live(),

            Select::make('default_node_id')
                ->label('Default node')
                ->relationship('defaultNode', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => ! $get('auto_deploy'))
                ->required(fn (Get $get) => ! $get('auto_deploy')),

            Select::make('allowed_node_ids')
                ->label('Allowed nodes')
                ->multiple()
                ->options(fn () => \App\Models\Node::pluck('name', 'id')->toArray())
                ->searchable()
                ->visible(fn (Get $get) => (bool) $get('auto_deploy'))
                ->required(fn (Get $get) => (bool) $get('auto_deploy'))
                ->helperText('At least one node must be selected when auto-deploy is on.'),

            TextInput::make('docker_image')
                ->label('Docker image override')
                ->maxLength(255)
                ->placeholder('Leave empty to use the egg default'),

            TextInput::make('port_count')
                ->label('Number of consecutive ports to allocate')
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(1)
                ->required()
                ->live()
                ->helperText('1 = standard. Multiple ports = game-specific protocols (RCON, Telnet…).'),

            Repeater::make('env_var_mapping')
                ->label('Environment variable mapping')
                ->helperText('Override how Pelican environment variables are computed at provisioning time.')
                ->schema([
                    TextInput::make('variable_name')
                        ->required()
                        ->alphaDash()
                        ->maxLength(100),
                    Select::make('type')
                        ->options([
                            'offset' => 'Offset (consecutive port from base)',
                            'random' => 'Random allocated port',
                            'static' => 'Static literal value',
                        ])
                        ->required()
                        ->live(),
                    Select::make('offset_value')
                        ->label('Which allocated port')
                        ->options(function (Get $get): array {
                            $count = (int) ($get('../../port_count') ?? 1);
                            $count = max(1, min(10, $count));
                            $options = [];
                            for ($i = 0; $i < $count; $i++) {
                                $options[$i] = $i === 0
                                    ? 'Main port (port + 0)'
                                    : 'Main port + '.$i;
                            }
                            return $options;
                        })
                        ->default(0)
                        ->visible(fn (Get $get) => $get('type') === 'offset')
                        ->required(fn (Get $get) => $get('type') === 'offset'),
                    TextInput::make('static_value')
                        ->visible(fn (Get $get) => $get('type') === 'static')
                        ->required(fn (Get $get) => $get('type') === 'static'),
                ])
                ->collapsible()
                ->collapsed()
                ->reorderable(true)
                ->defaultItems(0),

            Toggle::make('enable_oom_killer')
                ->label('Enable OOM Killer')
                ->helperText('Terminates the server if memory limit is exceeded.'),
            Toggle::make('start_on_completion')
                ->label('Start automatically after install')
                ->helperText('When OFF, the server stays stopped after installation completes.'),
            Toggle::make('skip_install_script')
                ->label('Skip install script')
                ->helperText('Do not run the egg install script on first boot.'),
            Toggle::make('dedicated_ip')
                ->label('Dedicated IP')
                ->helperText('Assign an IP not used by other servers.'),

            Section::make('Feature limits')
                ->columns(3)
                ->schema([
                    TextInput::make('feature_limits_databases')->label('Databases')->numeric()->default(0),
                    TextInput::make('feature_limits_backups')->label('Backups')->numeric()->default(3),
                    TextInput::make('feature_limits_allocations')->label('Allocations')->numeric()->default(1),
                ]),
        ];
    }
}
