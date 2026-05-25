<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource;

use App\Services\Bridge\IpVariableResolver;
use App\Support\EggVariableOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * "IP variable" sub-schema of the Pelican-config tab on
 * ServerConfigurationResource.
 *
 * Lets an admin push the provisioned server's public IP into a chosen egg
 * environment variable. The target-variable list is populated live from the
 * selected egg (EggVariableOptions) so the admin never types the name by hand.
 *
 * Two IP sources, resolved via Cloudflare DoH at provisioning time
 * (App\Services\Bridge\IpVariableResolver) :
 *   - node_fqdn        : resolve the node's FQDN
 *   - allocation_alias : resolve the default allocation's ip_alias
 *
 * These fields sit at the form root alongside `egg_id`, so they read the
 * selected egg with `$get('egg_id')` directly (no `../` traversal).
 */
final class IpVariableSchema
{
    public static function make(): Section
    {
        return Section::make(__('admin/server_configurations.ip_variable.section'))
            ->description(__('admin/server_configurations.ip_variable.description'))
            ->schema([
                Toggle::make('ip_variable_enabled')
                    ->label(__('admin/server_configurations.ip_variable.enabled'))
                    ->helperText(__('admin/server_configurations.ip_variable.enabled_help'))
                    ->live(),

                Select::make('ip_variable_name')
                    ->label(__('admin/server_configurations.ip_variable.variable'))
                    ->helperText(__('admin/server_configurations.ip_variable.variable_help'))
                    ->options(function (Get $get): array {
                        $options = EggVariableOptions::forEgg($get('egg_id'));
                        // Preserve a previously-saved value even if the egg's
                        // live variable list can't be fetched (Pelican down).
                        $current = $get('ip_variable_name');
                        if (is_string($current) && $current !== '' && ! array_key_exists($current, $options)) {
                            $options[$current] = $current;
                        }

                        return $options;
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('ip_variable_enabled'))
                    ->required(fn (Get $get): bool => (bool) $get('ip_variable_enabled')),

                Select::make('ip_variable_source')
                    ->label(__('admin/server_configurations.ip_variable.source'))
                    ->helperText(__('admin/server_configurations.ip_variable.source_help'))
                    ->options([
                        IpVariableResolver::SOURCE_NODE_FQDN => __('admin/server_configurations.ip_variable.source_node_fqdn'),
                        IpVariableResolver::SOURCE_ALLOCATION_ALIAS => __('admin/server_configurations.ip_variable.source_allocation_alias'),
                    ])
                    ->default(IpVariableResolver::SOURCE_NODE_FQDN)
                    ->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get('ip_variable_enabled'))
                    ->required(fn (Get $get): bool => (bool) $get('ip_variable_enabled')),
            ]);
    }
}
