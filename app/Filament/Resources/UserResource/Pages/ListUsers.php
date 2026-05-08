<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\Sync\SyncOrderGuard;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return SyncOrderGuard::orderHint().' — '.app(SyncOrderGuard::class)->statusLine();
    }

    protected function getHeaderActions(): array
    {
        $blockReason = app(SyncOrderGuard::class)->blockSyncUsers();

        return [
            Actions\CreateAction::make(),
            Actions\Action::make('syncUsers')
                ->label(__('admin/users.sync.label'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled($blockReason !== null)
                ->tooltip($blockReason)
                ->requiresConfirmation()
                ->modalHeading(__('admin/users.sync.modal_heading'))
                ->modalDescription(__('admin/users.sync.modal_description'))
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $comparison = $syncService->compareUsers();
                    $newCount = count($comparison->new);

                    if ($newCount === 0) {
                        Notification::make()
                            ->title(__('admin/users.sync.no_new'))
                            ->info()
                            ->send();

                        return;
                    }

                    $ids = array_map(fn (object $u): int => $u->id, $comparison->new);
                    $imported = $syncService->importUsers($ids);

                    Notification::make()
                        ->title(__('admin/users.sync.imported', ['count' => $imported]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
