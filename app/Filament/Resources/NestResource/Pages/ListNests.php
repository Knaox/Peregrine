<?php

namespace App\Filament\Resources\NestResource\Pages;

use App\Filament\Resources\NestResource;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNests extends ListRecords
{
    protected static string $resource = NestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncNests')
                ->label('Sync Nests')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $count = $syncService->syncEggs();

                    Notification::make()
                        ->title("Synced nests & {$count} eggs from Pelican")
                        ->success()
                        ->send();
                }),
        ];
    }
}
