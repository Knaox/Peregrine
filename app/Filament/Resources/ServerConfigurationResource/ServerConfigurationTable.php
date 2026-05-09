<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource;

use App\Models\ServerConfiguration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Table configuration for `ServerConfigurationResource` — columns, filters,
 * row / bulk actions. Extracted from the parent Resource to honour the
 * 300-line file rule.
 *
 * Delete behaviour : the FK on `servers.server_configuration_id` is
 * `nullOnDelete`, so removing a configuration never breaks already-
 * provisioned servers — they keep running with `server_configuration_id`
 * NULL. Admins can therefore garbage-collect orphan configurations safely.
 */
final class ServerConfigurationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters())
            ->recordActions(self::recordActions())
            ->toolbarActions(self::toolbarActions())
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(__('admin/server_configurations.resource.plural'))
            ->emptyStateDescription(__('admin/server_configurations.empty.description'));
    }

    /**
     * @return array<int, mixed>
     */
    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')->label(__('admin/_shell.fields.id'))->sortable(),
            Tables\Columns\TextColumn::make('internal_name')
                ->label(__('admin/server_configurations.fields.internal_name'))
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('egg.name')
                ->label(__('admin/_shell.fields.egg'))
                ->placeholder(__('admin/_shell.common.not_configured'))
                ->color(fn ($state) => $state === null ? 'warning' : null)
                ->icon(fn ($state) => $state === null ? 'heroicon-o-exclamation-triangle' : null)
                ->tooltip(fn ($state) => $state === null ? __('admin/server_configurations.tooltips.no_egg') : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('node.name')
                ->label(__('admin/_shell.fields.node'))
                ->placeholder(__('admin/_shell.common.auto'))
                ->tooltip(fn ($state) => $state === null ? __('admin/server_configurations.tooltips.auto_node') : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('ram')
                ->label(__('admin/server_configurations.fields.ram'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state).' MB')
                ->sortable(),
            Tables\Columns\TextColumn::make('cpu')
                ->label(__('admin/server_configurations.fields.cpu'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : $state.' %')
                ->sortable(),
            Tables\Columns\IconColumn::make('syncStatus')
                ->label(__('admin/_shell.fields.status'))
                ->getStateUsing(fn (ServerConfiguration $record) => $record->syncStatus())
                ->icon(fn (string $state): string => match ($state) {
                    'ready' => 'heroicon-o-check-circle',
                    'needs_config' => 'heroicon-o-exclamation-triangle',
                    default => 'heroicon-o-question-mark-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'ready' => 'success',
                    'needs_config' => 'warning',
                    default => 'gray',
                })
                ->tooltip(fn (string $state): string => match ($state) {
                    'ready' => __('admin/server_configurations.sync_status.ready'),
                    'needs_config' => __('admin/server_configurations.sync_status.needs_config'),
                    default => __('admin/_shell.common.unknown'),
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function filters(): array
    {
        return [
            Tables\Filters\Filter::make('needs_config')
                ->label(__('admin/server_configurations.filters.needs_config'))
                ->query(fn ($q) => $q->whereNull('egg_id')->orWhereNull('node_id')),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function recordActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading(fn (ServerConfiguration $record): string => __(
                    'admin/server_configurations.delete.modal_heading',
                    ['name' => $record->internal_name]
                ))
                ->modalDescription(function (ServerConfiguration $record): string {
                    $count = $record->servers()->count();
                    $base = $count === 0
                        ? __('admin/server_configurations.delete.no_servers')
                        : __('admin/server_configurations.delete.with_servers', ['count' => $count]);
                    return $base.' '.__('admin/server_configurations.delete.irreversible');
                })
                ->modalSubmitActionLabel(__('admin/server_configurations.delete.submit')),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function toolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->requiresConfirmation()
                    ->modalHeading(__('admin/server_configurations.delete_bulk.modal_heading'))
                    ->modalDescription(function ($records): string {
                        $totalServers = collect($records)->sum(
                            fn (ServerConfiguration $c) => $c->servers()->count()
                        );
                        $base = __('admin/server_configurations.delete_bulk.count', ['count' => $records->count()]);
                        if ($totalServers > 0) {
                            $base .= ' '.__('admin/server_configurations.delete_bulk.with_servers', ['count' => $totalServers]);
                        }
                        return $base.' '.__('admin/server_configurations.delete_bulk.irreversible');
                    })
                    ->modalSubmitActionLabel(__('admin/server_configurations.delete_bulk.submit')),
            ]),
        ];
    }
}
