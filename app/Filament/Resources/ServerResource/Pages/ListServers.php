<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Models\User;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('syncServers')
                ->label('Sync Servers')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Servers from Pelican')
                ->modalDescription('This will fetch all servers from Pelican and import any new ones into Peregrine.')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $comparison = $syncService->compareServers();
                    $newCount = count($comparison->new);

                    if ($newCount === 0) {
                        Notification::make()
                            ->title('No new servers found')
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
