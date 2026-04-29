<?php

namespace App\Filament\Resources\ServerPlanResource;

use App\Models\ServerPlan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Table configuration for `ServerPlanResource` — columns, filters, row /
 * bulk actions. Extracted from the parent Resource to honour the 300-line
 * file rule.
 *
 * Delete is intentionally available on this resource even though the
 * nominal flow is the Shop sending a DELETE through the Bridge (which
 * only deactivates the plan). If a plan is deleted in the Shop without
 * notifying Peregrine (network outage, manual DB cleanup, shop re-init),
 * the admin must be able to garbage-collect the orphan from here. The FK
 * on `servers.plan_id` is `nullOnDelete`, so removing a plan never breaks
 * already-provisioned servers — they keep running with plan_id NULL.
 */
final class ServerPlanTable
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
            ->emptyStateHeading(__('admin.resources.server_plans.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.plans'));
    }

    /**
     * @return array<int, mixed>
     */
    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')->label(__('admin.fields.id'))->sortable(),
            Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('shop_plan_id')
                ->label(__('admin.fields.shop_id'))
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('price_cents')
                ->label(__('admin.fields.price'))
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? '—' : number_format($state / 100, 2).' '.($record->currency ?? '')
                ),
            Tables\Columns\TextColumn::make('egg.name')
                ->label(__('admin.fields.egg'))
                ->placeholder(__('admin.common.not_configured'))
                ->color(fn ($state) => $state === null ? 'warning' : null)
                ->icon(fn ($state) => $state === null ? 'heroicon-o-exclamation-triangle' : null)
                ->tooltip(fn ($state) => $state === null ? __('admin.plans.tooltips.no_egg') : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('node.name')
                ->label(__('admin.fields.node'))
                ->placeholder(__('admin.common.auto'))
                ->tooltip(fn ($state) => $state === null ? __('admin.plans.tooltips.auto_node') : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('ram')
                ->label(__('admin.plans.fields.ram'))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state).' MB')
                ->sortable(),
            Tables\Columns\IconColumn::make('syncStatus')
                ->label(__('admin.fields.status'))
                ->getStateUsing(fn (ServerPlan $record) => $record->syncStatus())
                ->icon(fn (string $state): string => match ($state) {
                    'ready' => 'heroicon-o-check-circle',
                    'needs_config' => 'heroicon-o-exclamation-triangle',
                    'inactive' => 'heroicon-o-pause-circle',
                    'sync_error' => 'heroicon-o-x-circle',
                    default => 'heroicon-o-question-mark-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'ready' => 'success',
                    'needs_config' => 'warning',
                    'inactive' => 'gray',
                    'sync_error' => 'danger',
                    default => 'gray',
                })
                ->tooltip(fn (string $state): string => match ($state) {
                    'ready' => __('admin.plans.sync_status.ready'),
                    'needs_config' => __('admin.plans.sync_status.needs_config'),
                    'inactive' => __('admin.plans.sync_status.inactive'),
                    'sync_error' => __('admin.plans.sync_status.sync_error'),
                    default => __('admin.common.unknown'),
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function filters(): array
    {
        return [
            Tables\Filters\TernaryFilter::make('is_active')->label(__('admin.plans.filters.active')),
            Tables\Filters\Filter::make('needs_config')
                ->label(__('admin.plans.filters.needs_config'))
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
                ->modalHeading(fn (ServerPlan $record): string => __('admin.plans.delete.modal_heading', ['name' => $record->name]))
                ->modalDescription(function (ServerPlan $record): string {
                    $count = $record->servers()->count();
                    $base = $count === 0
                        ? __('admin.plans.delete.no_servers')
                        : __('admin.plans.delete.with_servers', ['count' => $count]);
                    return $base.' '.__('admin.plans.delete.irreversible');
                })
                ->modalSubmitActionLabel(__('admin.plans.delete.submit')),
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
                    ->modalHeading(__('admin.plans.delete_bulk.modal_heading'))
                    ->modalDescription(function ($records): string {
                        $totalServers = collect($records)->sum(fn (ServerPlan $p) => $p->servers()->count());
                        $base = __('admin.plans.delete_bulk.count', ['count' => $records->count()]);
                        if ($totalServers > 0) {
                            $base .= ' '.__('admin.plans.delete_bulk.with_servers', ['count' => $totalServers]);
                        }
                        return $base.' '.__('admin.plans.delete_bulk.irreversible');
                    })
                    ->modalSubmitActionLabel(__('admin.plans.delete_bulk.submit')),
            ]),
        ];
    }
}
