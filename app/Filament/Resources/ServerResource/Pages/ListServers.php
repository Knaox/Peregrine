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

                    // Build import array, matching Pelican userId to local user
                    $imports = array_map(fn (object $s): array => [
                        'pelican_server_id' => $s->id,
                        'user_id' => User::where('pelican_user_id', $s->userId)->first()?->id,
                    ], $comparison->new);

                    // Filter out servers where we couldn't match a user
                    $imports = array_filter($imports, fn (array $i): bool => $i['user_id'] !== null);
                    $skipped = $newCount - count($imports);

                    $imported = $syncService->importServers(array_values($imports));

                    Notification::make()
                        ->title("Imported {$imported} servers from Pelican")
                        ->success()
                        ->send();

                    if ($skipped > 0) {
                        Notification::make()
                            ->title("{$skipped} servers skipped (no matching user)")
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
