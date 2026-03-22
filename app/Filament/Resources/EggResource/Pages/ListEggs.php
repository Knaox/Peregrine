<?php

namespace App\Filament\Resources\EggResource\Pages;

use App\Filament\Resources\EggResource;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEggs extends ListRecords
{
    protected static string $resource = EggResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncEggs')
                ->label('Sync Eggs')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $count = $syncService->syncEggs();

                    Notification::make()
                        ->title("Synced {$count} eggs from Pelican")
                        ->success()
                        ->send();
                }),
        ];
    }
}
