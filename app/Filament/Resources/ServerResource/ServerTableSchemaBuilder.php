<?php

namespace App\Filament\Resources\ServerResource;

use App\Filament\Actions\ResourceDeleteAction;
use App\Jobs\ProvisionServerJob;
use App\Models\Server;
use App\Services\Bridge\BridgeModeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            ->recordActions($recordActions)
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * @return array<int, mixed>
     */
    private static function columns(bool $isShopStripe): array
    {
        $columns = [
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Owner')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->formatStateUsing(function (string $state, Server $record): string {
                    if ($state === 'provisioning' && self::isStuckProvisioning($record)) {
                        return 'provisioning (stuck)';
                    }
                    return $state;
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
                        return 'This server has been awaiting the Pelican install-completion webhook for over 30 minutes. '
                            . 'Most likely the events `updated: Server` and `event: Server\\Installed` are not ticked '
                            . 'in your Pelican /admin/webhooks. Check /admin/pelican-webhook-logs for incoming events '
                            . 'and /docs/pelican-webhook for the configuration guide.';
                    }
                    return null;
                })
                ->sortable(),
            Tables\Columns\TextColumn::make('egg.name')->label('Egg')->searchable()->sortable(),
        ];

        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('plan.name')
                ->label('Plan')->sortable()->placeholder('—');
        }

        $columns[] = Tables\Columns\TextColumn::make('pelican_server_id')
            ->label('Pelican ID')->sortable()->placeholder('—');

        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('stripe_subscription_id')
                ->label('Stripe Sub.')->sortable()
                ->toggleable(isToggledHiddenByDefault: true)->placeholder('—');
            $columns[] = Tables\Columns\TextColumn::make('scheduled_deletion_at')
                ->label('Scheduled deletion')->dateTime()->sortable()->placeholder('—')
                ->color(fn ($state) => $state === null ? null : 'danger')
                ->tooltip(fn ($state) => $state === null
                    ? null
                    : 'This server will be hard-deleted at the date shown. Use the action menu → Cancel scheduled deletion to keep it.');
        }

        $columns[] = Tables\Columns\TextColumn::make('created_at')
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
                ->options([
                    'active' => 'Active',
                    'running' => 'Running',
                    'stopped' => 'Stopped',
                    'suspended' => 'Suspended',
                    'terminated' => 'Terminated',
                    'offline' => 'Offline',
                ]),
        ];

        if ($isShopStripe) {
            $filters[] = Tables\Filters\TernaryFilter::make('plan_id')->label('Has Plan')->nullable();
            $filters[] = Tables\Filters\TernaryFilter::make('stripe_subscription_id')->label('Has Stripe Subscription')->nullable();
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
            ->label('Retry provisioning')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (Server $record): bool => $record->status === 'provisioning_failed'
                && $record->plan_id !== null
                && $record->idempotency_key !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (Server $record): string => "Retry provisioning for \"{$record->name}\"?")
            ->modalDescription('Re-dispatches a ProvisionServerJob with the same idempotency key — the local row is reused, no duplicate is created. Status flips back to "provisioning". Make sure the queue worker is running, otherwise the job will sit in `jobs` indefinitely.')
            ->modalSubmitActionLabel('Retry now')
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
                    ->title('Retry dispatched')
                    ->body("ProvisionServerJob queued for \"{$record->name}\". Watch the queue logs for progress.")
                    ->success()
                    ->send();
            });

        if ($isShopStripe) {
            $recordActions[] = Action::make('cancelScheduledDeletion')
                ->label('Cancel scheduled deletion')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (Server $record): bool => $record->scheduled_deletion_at !== null)
                ->requiresConfirmation()
                ->modalHeading(fn (Server $record): string => "Cancel scheduled deletion for \"{$record->name}\"?")
                ->modalDescription(fn (Server $record): string => sprintf(
                    'Hard deletion is currently scheduled for %s. Cancelling will keep the server in suspended state. To re-enable the customer\'s access, also unsuspend it from Pelican.',
                    $record->scheduled_deletion_at?->format('Y-m-d H:i') ?? '?',
                ))
                ->modalSubmitActionLabel('Yes, keep this server')
                ->action(function (Server $record): void {
                    $record->update(['scheduled_deletion_at' => null]);
                    Notification::make()
                        ->title('Scheduled deletion cancelled')
                        ->body("Server \"{$record->name}\" will not be hard-deleted. It remains suspended — unsuspend manually if the customer regains access.")
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
