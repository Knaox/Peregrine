<?php

namespace App\Filament\Resources\ServerResource;

use App\Filament\Actions\ResourceDeleteAction;
use App\Jobs\ProvisionServerJob;
use App\Models\Server;
use App\Services\Bridge\BridgeModeService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Builds the Filament table schema for ServerResource. Extracted to keep
 * the parent Resource under the 300-line plafond CLAUDE.md.
 *
 * Includes the "stuck provisioning" badge logic (servers stalled in
 * `provisioning` for > 30 min, surfaces missing webhook config).
 */
final class ServerTableSchemaBuilder
{
    public static function build(Table $table): Table
    {
        $isShopStripe = app(BridgeModeService::class)->isShopStripe();

        $columns = self::columns($isShopStripe);
        $filters = self::filters($isShopStripe);
        $recordActions = self::recordActions($isShopStripe);

        return $table
            ->columns($columns)
            ->filters($filters)
            ->recordActions([
                ActionGroup::make($recordActions)
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size(Size::Small)
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-server-stack')
            ->emptyStateHeading(__('admin.resources.servers.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.servers'));
    }

    /**
     * @return array<int, mixed>
     */
    private static function columns(bool $isShopStripe): array
    {
        $columns = [
            Tables\Columns\TextColumn::make('id')->label(__('admin.fields.id'))->sortable(),
            Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label(__('admin.fields.owner'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('status')
                ->label(__('admin.fields.status'))
                ->badge()
                ->formatStateUsing(function (string $state, Server $record): string {
                    if ($state === 'provisioning' && self::isStuckProvisioning($record)) {
                        return __('admin.statuses.provisioning_stuck');
                    }
                    $key = 'admin.statuses.' . $state;
                    $translated = __($key);
                    return $translated === $key ? $state : $translated;
                })
                ->color(function (string $state, Server $record): string {
                    if ($state === 'provisioning' && self::isStuckProvisioning($record)) {
                        return 'danger';
                    }
                    return match ($state) {
                        'active', 'running' => 'success',
                        'stopped', 'provisioning' => 'warning',
                        'suspended', 'terminated', 'provisioning_failed' => 'danger',
                        'offline' => 'gray',
                        default => 'gray',
                    };
                })
                ->tooltip(function (string $state, Server $record): ?string {
                    if ($state === 'provisioning' && self::isStuckProvisioning($record)) {
                        return __('admin.servers.tooltips.stuck');
                    }
                    return null;
                })
                ->sortable(),
            Tables\Columns\TextColumn::make('egg.name')->label(__('admin.fields.egg'))->searchable()->sortable(),
        ];

        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('plan.name')
                ->label(__('admin.fields.plan'))->sortable()->placeholder('—');
        }

        $columns[] = Tables\Columns\TextColumn::make('pelican_server_id')
            ->label(__('admin.fields.pelican_id'))->sortable()->placeholder('—');

        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('stripe_subscription_id')
                ->label(__('admin.fields.stripe_subscription'))->sortable()
                ->toggleable(isToggledHiddenByDefault: true)->placeholder('—');
            $columns[] = Tables\Columns\TextColumn::make('scheduled_deletion_at')
                ->label(__('admin.fields.scheduled_deletion'))->dateTime()->sortable()->placeholder('—')
                ->color(fn ($state) => $state === null ? null : 'danger')
                ->tooltip(fn ($state) => $state === null
                    ? null
                    : __('admin.servers.tooltips.scheduled_deletion'));
        }

        $columns[] = Tables\Columns\TextColumn::make('created_at')
            ->label(__('admin.fields.created_at'))
            ->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true);

        return $columns;
    }

    /**
     * @return array<int, mixed>
     */
    private static function filters(bool $isShopStripe): array
    {
        $filters = [
            Tables\Filters\SelectFilter::make('status')
                ->label(__('admin.fields.status'))
                ->options([
                    'active' => __('admin.statuses.active'),
                    'running' => __('admin.statuses.running'),
                    'stopped' => __('admin.statuses.stopped'),
                    'suspended' => __('admin.statuses.suspended'),
                    'terminated' => __('admin.statuses.terminated'),
                    'provisioning' => __('admin.statuses.provisioning'),
                    'provisioning_failed' => __('admin.statuses.provisioning_failed'),
                    'offline' => __('admin.statuses.offline'),
                ])
                ->multiple(),
            Tables\Filters\SelectFilter::make('user_id')
                ->label(__('admin.fields.owner'))
                ->relationship('user', 'name')
                ->searchable()
                ->preload(),
            Tables\Filters\SelectFilter::make('egg_id')
                ->label(__('admin.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload(),
        ];

        if ($isShopStripe) {
            $filters[] = Tables\Filters\TernaryFilter::make('plan_id')->label(__('admin.fields.has_plan'))->nullable();
            $filters[] = Tables\Filters\TernaryFilter::make('stripe_subscription_id')->label(__('admin.fields.has_stripe_subscription'))->nullable();
            $filters[] = Tables\Filters\TernaryFilter::make('scheduled_deletion_at')->label(__('admin.fields.scheduled_for_deletion'))->nullable();
        }

        return $filters;
    }

    /**
     * @return array<int, mixed>
     */
    private static function recordActions(bool $isShopStripe): array
    {
        $recordActions = [EditAction::make()];

        $recordActions[] = Action::make('retryProvisioning')
            ->label(__('admin.servers.retry.label'))
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (Server $record): bool => $record->status === 'provisioning_failed'
                && $record->plan_id !== null
                && $record->idempotency_key !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (Server $record): string => __('admin.servers.retry.modal_heading', ['name' => $record->name]))
            ->modalDescription(__('admin.servers.retry.modal_description'))
            ->modalSubmitActionLabel(__('admin.servers.retry.submit'))
            ->action(function (Server $record): void {
                $record->update([
                    'status' => 'provisioning',
                    'provisioning_error' => null,
                ]);
                ProvisionServerJob::dispatch(
                    planId: (int) $record->plan_id,
                    userId: (int) $record->user_id,
                    idempotencyKey: (string) $record->idempotency_key,
                    serverNameOverride: $record->name,
                    stripeSubscriptionId: $record->stripe_subscription_id,
                    paymentIntentId: $record->payment_intent_id,
                );
                Notification::make()
                    ->title(__('admin.servers.retry.notification_title'))
                    ->body(__('admin.servers.retry.notification_body', ['name' => $record->name]))
                    ->success()
                    ->send();
            });

        if ($isShopStripe) {
            $recordActions[] = Action::make('cancelScheduledDeletion')
                ->label(__('admin.servers.cancel_deletion.label'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (Server $record): bool => $record->scheduled_deletion_at !== null)
                ->requiresConfirmation()
                ->modalHeading(fn (Server $record): string => __('admin.servers.cancel_deletion.modal_heading', ['name' => $record->name]))
                ->modalDescription(fn (Server $record): string => __('admin.servers.cancel_deletion.modal_description', [
                    'date' => $record->scheduled_deletion_at?->format('Y-m-d H:i') ?? '?',
                ]))
                ->modalSubmitActionLabel(__('admin.servers.cancel_deletion.submit'))
                ->action(function (Server $record): void {
                    $record->update(['scheduled_deletion_at' => null]);
                    Notification::make()
                        ->title(__('admin.servers.cancel_deletion.notification_title'))
                        ->body(__('admin.servers.cancel_deletion.notification_body', ['name' => $record->name]))
                        ->success()
                        ->send();
                });
        }

        $recordActions[] = ResourceDeleteAction::row();

        return $recordActions;
    }

    private static function isStuckProvisioning(Server $server): bool
    {
        if ($server->status !== 'provisioning') {
            return false;
        }
        if ($server->created_at === null) {
            return false;
        }
        return $server->created_at->lt(now()->subMinutes(30));
    }
}
