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
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('shop_plan_id')
                ->label('Shop ID')
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('price_cents')
                ->label('Price')
                ->formatStateUsing(fn ($state, ServerPlan $record) =>
                    $state === null ? '—' : number_format($state / 100, 2).' '.($record->currency ?? '')
                ),
            Tables\Columns\TextColumn::make('egg.name')
                ->label('Egg')
                ->placeholder(__('admin.common.not_configured'))
                ->color(fn ($state) => $state === null ? 'warning' : null)
                ->icon(fn ($state) => $state === null ? 'heroicon-o-exclamation-triangle' : null)
                ->tooltip(fn ($state) => $state === null
                    ? 'No egg configured — provisioning will fail until you pick one in the plan edit page.'
                    : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('node.name')
                ->label('Node')
                ->placeholder(__('admin.common.auto'))
                ->tooltip(fn ($state) => $state === null
                    ? 'Auto: a node from the allowed list will be picked at provisioning time based on resources.'
                    : null)
                ->sortable(),
            Tables\Columns\TextColumn::make('ram')
                ->label('RAM')
                ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state).' MB')
                ->sortable(),
            Tables\Columns\IconColumn::make('syncStatus')
                ->label('Status')
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
                    'ready' => 'Ready to provision (egg + node configured)',
                    'needs_config' => 'Configure egg + node before this plan can provision servers',
                    'inactive' => 'Plan deactivated by the Shop — no new purchases possible',
                    'sync_error' => 'Last Bridge sync from the Shop failed — check the audit log',
                    default => 'Unknown',
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function filters(): array
    {
        return [
            Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            Tables\Filters\Filter::make('needs_config')
                ->label('Needs config')
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
                ->modalHeading(fn (ServerPlan $record): string => "Delete plan \"{$record->name}\"?")
                ->modalDescription(function (ServerPlan $record): string {
                    $count = $record->servers()->count();
                    $base = $count === 0
                        ? 'No server is currently linked to this plan.'
                        : "{$count} provisioned server(s) currently reference this plan. Their `plan_id` will be set to NULL — the servers keep running, they just lose their billing reference.";
                    return $base.' This is irreversible. Use the Shop\'s DELETE endpoint instead when possible (it just deactivates).';
                })
                ->modalSubmitActionLabel('Yes, delete permanently'),
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
                    ->modalHeading('Delete selected plans?')
                    ->modalDescription(function ($records): string {
                        $totalServers = collect($records)->sum(fn (ServerPlan $p) => $p->servers()->count());
                        $base = "Deleting {$records->count()} plan(s).";
                        if ($totalServers > 0) {
                            $base .= " {$totalServers} provisioned server(s) will lose their plan reference (set to NULL). The servers keep running.";
                        }
                        return $base.' This is irreversible.';
                    })
                    ->modalSubmitActionLabel('Yes, delete all'),
            ]),
        ];
    }
}
