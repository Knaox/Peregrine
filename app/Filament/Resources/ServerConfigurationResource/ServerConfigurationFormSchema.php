<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource;

use App\Models\Node;
use App\Models\ResourceTemplate;
use App\Support\EggVariableOptions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

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

    /**
     * Replaces the legacy inline-specs section. The form now exposes a
     * Select on `resource_template_id`, populated from the
     * `ResourceTemplate` table. A read-only Placeholder mirrors the
     * picked template's specs so the admin sees what they're binding
     * without leaving the form.
     *
     * @return array<int, mixed>
     */
    private static function resourceFields(): array
    {
        return [
            Select::make('resource_template_id')
                ->label(__('admin/server_configurations.fields.resource_template'))
                ->helperText(__('admin/server_configurations.helpers.resource_template'))
                ->relationship('resourceTemplate', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->required()
                ->placeholder(__('admin/server_configurations.placeholders.resource_template')),

            Placeholder::make('resource_template_preview')
                ->label(__('admin/server_configurations.fields.resource_template_preview'))
                ->content(fn (Get $get) => self::renderTemplatePreview($get('resource_template_id'))),
        ];
    }

    /**
     * Read-only preview of the picked ResourceTemplate's specs. Empty
     * placeholder when nothing is picked yet.
     */
    private static function renderTemplatePreview(mixed $templateId): HtmlString
    {
        if (! $templateId) {
            return new HtmlString(
                '<em class="opacity-60">'.e(__('admin/server_configurations.helpers.resource_template_empty')).'</em>'
            );
        }
        $tpl = ResourceTemplate::find((int) $templateId);
        if ($tpl === null) {
            return new HtmlString(
                '<em class="opacity-60">'.e(__('admin/server_configurations.helpers.resource_template_not_found')).'</em>'
            );
        }
        $rows = [
            ['RAM', number_format((int) $tpl->ram).' MB'],
            ['CPU', $tpl->cpu.' %'],
            ['Disk', number_format((int) $tpl->disk).' MB'],
            ['Swap', number_format((int) ($tpl->swap_mb ?? 0)).' MB'],
            ['I/O weight', (string) ($tpl->io_weight ?? 500)],
            ['CPU pinning', $tpl->cpu_pinning ?: '—'],
        ];
        $html = '<dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">';
        foreach ($rows as [$k, $v]) {
            $html .= '<dt class="opacity-60">'.e($k).'</dt><dd>'.e($v).'</dd>';
        }
        $html .= '</dl>';

        return new HtmlString($html);
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
                // Live so the env_var_mapping repeater and the IP-variable
                // picker can list THIS egg's variables (EggVariableOptions).
                ->live()
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
                ->options(fn () => Node::pluck('name', 'id')->toArray())
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
                    Select::make('variable_name')
                        ->label(__('admin/server_configurations.fields.variable_name'))
                        ->helperText(__('admin/server_configurations.helpers.variable_name'))
                        ->options(function (Get $get): array {
                            // `../../egg_id` : two levels up from a repeater
                            // item field to the form root, same traversal the
                            // offset_value field uses for `../../port_count`.
                            $options = EggVariableOptions::forEgg($get('../../egg_id'));
                            // Keep a previously-saved value selectable even if
                            // it's not in the egg's live list (egg changed, or
                            // Pelican unreachable while editing).
                            $current = $get('variable_name');
                            if (is_string($current) && $current !== '' && ! array_key_exists($current, $options)) {
                                $options[$current] = $current;
                            }

                            return $options;
                        })
                        ->searchable()
                        ->required(),
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

            IpVariableSchema::make(),

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
