<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Models\User;
use App\Services\Sync\SyncOrderGuard;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    public function getSubheading(): ?string
    {
        return SyncOrderGuard::ORDER_HINT.' — '.app(SyncOrderGuard::class)->statusLine();
    }

    protected function getHeaderActions(): array
    {
        $blockReason = app(SyncOrderGuard::class)->blockSyncServers();

        return [
            Actions\CreateAction::make(),
            Actions\Action::make('syncServers')
                ->label(__('admin.resource_pages.sync_servers.label'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled($blockReason !== null)
                ->tooltip($blockReason)
                ->requiresConfirmation()
                ->modalHeading(__('admin.resource_pages.sync_servers.modal_heading'))
                ->modalDescription(__('admin.resource_pages.sync_servers.modal_description'))
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $comparison = $syncService->compareServers();
                    $newCount = count($comparison->new);

                    if ($newCount === 0) {
                        Notification::make()
                            ->title(__('admin.resource_pages.sync_servers.no_new'))
                            ->info()
                            ->send();

                        return;
                    }

                    // Current admin as fallback owner
                    $fallbackUserId = auth()->id();

                    $imports = array_map(function (object $s) use ($fallbackUserId): array {
                        // Try matching by pelican_user_id first, then by email via Pelican user
                        $localUser = User::where('pelican_user_id', $s->userId)->first();

                        return [
                            'pelican_server_id' => $s->id,
                            'user_id' => $localUser?->id ?? $fallbackUserId,
                        ];
                    }, $comparison->new);

                    $imported = $syncService->importServers($imports);

                    Notification::make()
                        ->title("Imported {$imported} servers from Pelican")
                        ->success()
                        ->send();
                }),
        ];
    }
}
