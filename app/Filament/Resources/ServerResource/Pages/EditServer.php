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
     * Reflect a manual status change to Pelican: setting "suspended" suspends
     * the server, setting "active" on a previously suspended server unsuspends
     * it. The Pelican call runs BEFORE the DB write so a failure aborts the save
     * (no inconsistent state). Any other status value is saved as-is with no
     * Pelican side-effect.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newStatus = $data['status'] ?? null;
        $oldStatus = $record->status;

        if ($newStatus !== $oldStatus && $record->pelican_server_id !== null) {
            $pelican = app(PelicanApplicationService::class);
            try {
                if ($newStatus === 'suspended') {
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
