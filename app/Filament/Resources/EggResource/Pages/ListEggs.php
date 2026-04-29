<?php

namespace App\Filament\Resources\EggResource\Pages;

use App\Filament\Resources\EggResource;
use App\Services\Sync\SyncOrderGuard;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEggs extends ListRecords
{
    protected static string $resource = EggResource::class;

    public function getSubheading(): ?string
    {
        return SyncOrderGuard::orderHint().' — '.app(SyncOrderGuard::class)->statusLine();
    }

    protected function getHeaderActions(): array
    {
        $blockReason = app(SyncOrderGuard::class)->blockSyncEggs();

        return [
            Actions\Action::make('syncEggs')
                ->label(__('admin.resource_pages.sync_eggs.label'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled($blockReason !== null)
                ->tooltip($blockReason)
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $count = $syncService->syncEggs();

                    Notification::make()
                        ->title(__('admin.resource_pages.sync_eggs.success', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
