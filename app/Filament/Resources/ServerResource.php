<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDeleteAction;
use App\Filament\Resources\ServerResource\Pages;
use App\Jobs\ProvisionServerJob;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isShopStripe = app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();

        $configurationFields = [
            Select::make('egg_id')
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required(),
        ];

        // Plans only exist in Shop+Stripe mode (Shop pushes the catalogue).
        // Hide the picker entirely in Disabled / Paymenter modes.
        if ($isShopStripe) {
            $configurationFields[] = Select::make('plan_id')
                ->relationship('plan', 'name')
                ->searchable()
                ->preload()
                ->nullable();
        }

        $sections = [
            Section::make('Server Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'running' => 'Running',
                            'stopped' => 'Stopped',
                            'suspended' => 'Suspended',
                            'terminated' => 'Terminated',
                            'offline' => 'Offline',
                        ])
                        ->required(),
                    TextInput::make('pelican_server_id')
                        ->label('Pelican Server ID')
                        ->numeric(),
                ])->columns(2),

            Section::make('Configuration')
                ->schema($configurationFields)
                ->columns(2),
        ];

        // Billing section (Stripe Subscription ID + Payment Intent ID) is
        // meaningless outside Shop+Stripe mode — hide the whole section.
        if ($isShopStripe) {
            $sections[] = Section::make('Billing')
                ->schema([
                    TextInput::make('stripe_subscription_id')
                        ->label('Stripe Subscription ID')
                        ->maxLength(255)
                        ->nullable(),
                    TextInput::make('payment_intent_id')
                        ->label('Payment Intent ID')
                        ->maxLength(255)
                        ->nullable()
                        ->disabled(),
                ])->columns(2);
        }

        return $schema->schema($sections);
    }

    public static function table(Table $table): Table
    {
        $isShopStripe = app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();

        $columns = [
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Owner')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->formatStateUsing(function (string $state, Server $record): string {
                    // "Stuck" suffix flags a provisioning server that's been
                    // waiting on the Pelican webhook for more than 30 min —
                    // most likely the admin forgot to tick `updated: Server`
                    // in /admin/webhooks on the Pelican side.
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

        // Plan / Stripe / scheduled deletion columns only make sense in
        // Shop+Stripe mode (Paymenter manages its own billing → these
        // fields stay null on every server).
        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('plan.name')
                ->label('Plan')
                ->sortable()
                ->placeholder('—');
        }

        $columns[] = Tables\Columns\TextColumn::make('pelican_server_id')
            ->label('Pelican ID')
            ->sortable()
            ->placeholder('—');

        if ($isShopStripe) {
            $columns[] = Tables\Columns\TextColumn::make('stripe_subscription_id')
                ->label('Stripe Sub.')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder('—');
            $columns[] = Tables\Columns\TextColumn::make('scheduled_deletion_at')
                ->label('Scheduled deletion')
                ->dateTime()
                ->sortable()
                ->placeholder('—')
                ->color(fn ($state) => $state === null ? null : 'danger')
                ->tooltip(fn ($state) => $state === null
                    ? null
                    : 'This server will be hard-deleted at the date shown. Use the action menu → Cancel scheduled deletion to keep it.');
        }

        $columns[] = Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

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
            $filters[] = Tables\Filters\TernaryFilter::make('plan_id')
                ->label('Has Plan')
                ->nullable();
            $filters[] = Tables\Filters\TernaryFilter::make('stripe_subscription_id')
                ->label('Has Stripe Subscription')
                ->nullable();
        }

        $recordActions = [EditAction::make()];

        // Retry provisioning is available whenever a server ended up in
        // `provisioning_failed` AND was driven by a plan (i.e. originated
        // from ProvisionServerJob). Manually-imported servers without a plan
        // can't be retried — there's nothing to re-run.
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
            // Cancel-scheduled-deletion is a Stripe lifecycle recovery action —
            // hide it entirely outside Shop+Stripe mode.
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

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Server is "stuck in provisioning" when status='provisioning' and
     * created_at is older than the install grace window (30 min). The
     * webhook normally flips status within seconds — anything past 30 min
     * means the Pelican-side `updated: Server` event is not configured
     * (the canonical install-completion signal since Phase 1).
     */
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
