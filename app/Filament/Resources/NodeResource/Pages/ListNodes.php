<?php

namespace App\Filament\Resources\NodeResource\Pages;

use App\Filament\Resources\NodeResource;
use App\Services\Sync\SyncOrderGuard;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

    public function getSubheading(): ?string
    {
        return SyncOrderGuard::orderHint().' — '.app(SyncOrderGuard::class)->statusLine();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncNodes')
                ->label(__('admin/nodes.sync.label'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $count = $syncService->syncNodes();

                    Notification::make()
                        ->title(__('admin/nodes.sync.success', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
