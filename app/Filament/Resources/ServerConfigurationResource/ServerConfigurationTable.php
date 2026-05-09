<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource;

use App\Actions\Admin\DuplicateServerConfigurationAction;
use App\Filament\Resources\ServerConfigurationResource;
use App\Models\ServerConfiguration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            self::duplicateAction(),
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
     * Row action that clones a configuration, generating a unique
     * `internal_name` (`-copy`, `-copy-2`, …) and redirecting the admin
     * straight to the edit page so they can tweak the new copy without
     * a page-load round-trip.
     *
     * Pivot links to shops are NOT copied — the clone is invisible to
     * every shop until explicitly authorized (same pattern as creating
     * a fresh configuration).
     */
    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label(__('admin/server_configurations.duplicate.label'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (ServerConfiguration $record): string => __(
                'admin/server_configurations.duplicate.modal_heading',
                ['name' => $record->internal_name]
            ))
            ->modalDescription(__('admin/server_configurations.duplicate.modal_description'))
            ->modalSubmitActionLabel(__('admin/server_configurations.duplicate.submit'))
            ->action(function (ServerConfiguration $record) {
                $clone = app(DuplicateServerConfigurationAction::class)($record);

                Notification::make()
                    ->title(__('admin/server_configurations.duplicate.notification_title'))
                    ->body(__('admin/server_configurations.duplicate.notification_body', [
                        'name' => $clone->internal_name,
                    ]))
                    ->success()
                    ->send();

                return redirect()->to(ServerConfigurationResource::getUrl('edit', ['record' => $clone]));
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function toolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                self::duplicateBulkAction(),
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

    /**
     * Bulk duplicate. Each selected configuration is cloned independently
     * via the same `DuplicateServerConfigurationAction` used by the row
     * action, so the naming logic (`-copy`, `-copy-2`, …) stays consistent
     * with what an admin gets when duplicating one row at a time.
     *
     * Pivots are NOT carried over (cohérent avec la duplication unitaire) :
     * each clone is invisible to every shop until explicitly authorized.
     */
    private static function duplicateBulkAction(): BulkAction
    {
        return BulkAction::make('duplicate')
            ->label(__('admin/server_configurations.duplicate_bulk.label'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin/server_configurations.duplicate_bulk.modal_heading'))
            ->modalDescription(fn ($records): string => __(
                'admin/server_configurations.duplicate_bulk.modal_description',
                ['count' => $records->count()]
            ))
            ->modalSubmitActionLabel(__('admin/server_configurations.duplicate_bulk.submit'))
            ->action(function ($records) {
                $duplicator = app(DuplicateServerConfigurationAction::class);
                $count = 0;
                foreach ($records as $record) {
                    $duplicator($record);
                    $count++;
                }

                Notification::make()
                    ->title(__('admin/server_configurations.duplicate_bulk.notification_title'))
                    ->body(__('admin/server_configurations.duplicate_bulk.notification_body', ['count' => $count]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
