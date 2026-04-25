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
        return SyncOrderGuard::ORDER_HINT_FR.' — '.app(SyncOrderGuard::class)->statusLine();
    }

    protected function getHeaderActions(): array
    {
        $blockReason = app(SyncOrderGuard::class)->blockSyncUsers();

        return [
            Actions\CreateAction::make(),
            Actions\Action::make('syncUsers')
                ->label('Sync Users')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled($blockReason !== null)
                ->tooltip($blockReason)
                ->requiresConfirmation()
                ->modalHeading('Sync Users from Pelican')
                ->modalDescription('This will fetch all users from Pelican and import any new ones into Peregrine.')
                ->action(function (): void {
                    $syncService = app(SyncService::class);
                    $comparison = $syncService->compareUsers();
                    $newCount = count($comparison->new);

                    if ($newCount === 0) {
                        Notification::make()
                            ->title('No new users found')
                            ->info()
                            ->send();

                        return;
                    }

                    $ids = array_map(fn (object $u): int => $u->id, $comparison->new);
                    $imported = $syncService->importUsers($ids);

                    Notification::make()
                        ->title("Imported {$imported} users from Pelican")
                        ->success()
                        ->send();
                }),
        ];
    }
}
