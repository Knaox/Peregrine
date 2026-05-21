<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Services\Pelican\PelicanApplicationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * The `status` column doubles as the live Pelican power state (running /
     * stopped / offline) for non-suspended servers, refreshed by
     * SyncServerStatusJob. The form only exposes the manageable lifecycle
     * states (active / suspended / terminated), so collapse any power/transient
     * value to "active" (= not suspended) so the Select shows a valid option.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! in_array($data['status'] ?? null, ['suspended', 'terminated'], true)) {
            $data['status'] = 'active';
        }

        return $data;
    }

    /**
     * Setting the status drives Pelican : "suspended" suspends the server,
     * "active" unsuspends it (active = unsuspended). The Pelican call runs
     * BEFORE the DB write so a failure aborts the save and keeps both sides
     * consistent. Other transitions (e.g. → terminated) just persist.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newStatus = $data['status'] ?? null;
        $oldStatus = $record->status;

        // "active" in the select is the coerced display for ANY non-suspended
        // live state. So if the admin leaves it on "active" for a server that
        // was already a live power state (running/stopped/offline/provisioning…),
        // preserve the real status instead of clobbering it to 'active' — the
        // frontend card's live status line relies on running/stopped/offline.
        if ($newStatus === 'active' && ! in_array($oldStatus, ['suspended', 'terminated'], true)) {
            $data['status'] = $oldStatus;
        }

        if ($record->pelican_server_id !== null) {
            $pelican = app(PelicanApplicationService::class);
            try {
                if ($newStatus === 'suspended' && $oldStatus !== 'suspended') {
                    $pelican->suspendServer($record->pelican_server_id);
                } elseif ($newStatus === 'active' && $oldStatus === 'suspended') {
                    $pelican->unsuspendServer($record->pelican_server_id);
                }
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('admin/servers.status_change.pelican_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                throw new Halt();
            }
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
