<?php

namespace App\Filament\Resources\ServerPlanResource\Pages;

use App\Filament\Resources\ServerPlanResource;
use App\Jobs\ProvisionServerJob;
use App\Models\ServerPlan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditServerPlan extends EditRecord
{
    protected static string $resource = ServerPlanResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Debug-only action : queues a real Pelican server creation from this
        // plan, attached to the currently-logged-in admin's Pelican user.
        // The actual work happens in ProvisionServerJob (queued, retried with
        // backoff, idempotent). The admin sees the new row appear in
        // /admin/servers within seconds with status='provisioning' → 'active'.
        if (config('app.debug')) {
            $actions[] = Action::make('createTestServer')
                ->label('Create test server')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->visible(fn (): bool => (bool) config('app.debug'))
                ->disabled(fn (ServerPlan $record): bool => ! $record->isReadyToProvision())
                ->tooltip(fn (ServerPlan $record): ?string => $record->isReadyToProvision()
                    ? 'Queue a real Pelican server creation from this plan (debug mode only).'
                    : 'Configure egg + node first.')
                ->requiresConfirmation()
                ->modalHeading('Provision a test server?')
                ->modalDescription(fn (ServerPlan $record): string => sprintf(
                    'A real Pelican server will be created on node #%s using egg #%s, attached to your admin account. The job runs in the background — check /admin/servers in a few seconds.',
                    $record->node_id ?? $record->default_node_id ?? ($record->allowed_node_ids[0] ?? '?'),
                    $record->egg_id,
                ))
                ->modalSubmitActionLabel('Yes, queue now')
                ->action(function (ServerPlan $record): void {
                    $key = 'manual-test-'.now()->getTimestampMs().'-'.Str::random(8);

                    ProvisionServerJob::dispatch(
                        planId: $record->id,
                        userId: auth()->id(),
                        idempotencyKey: $key,
                    );

                    Notification::make()
                        ->title('Test server queued')
                        ->body('The provisioning job has been dispatched. Check /admin/servers — the row will appear immediately with status "provisioning" and flip to "active" once Pelican confirms (a few seconds). If Pelican is unreachable, the job retries automatically with backoff [60s, 5min, 15min].')
                        ->success()
                        ->persistent()
                        ->send();
                });
        }

        // No DeleteAction — plans are deactivated by the Shop only.
        return $actions;
    }
}
