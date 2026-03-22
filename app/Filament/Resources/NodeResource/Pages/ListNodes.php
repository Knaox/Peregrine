<?php

namespace App\Filament\Resources\NodeResource\Pages;

use App\Filament\Resources\NodeResource;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncNodes')
                ->label('Sync Nodes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $count = $syncService->syncNodes();

                    Notification::make()
                        ->title("Synced {$count} nodes from Pelican")
                        ->success()
                        ->send();
                }),
        ];
    }
}
