<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleStatus')
                ->label(fn (Shop $record): string => $record->status === 'active'
                    ? __('admin/shops.actions.suspend')
                    : __('admin/shops.actions.resume'))
                ->color(fn (Shop $record): string => $record->status === 'active' ? 'warning' : 'success')
                ->icon(fn (Shop $record): string => $record->status === 'active'
                    ? 'heroicon-o-pause-circle'
                    : 'heroicon-o-play-circle')
                ->requiresConfirmation()
                ->action(function (Shop $record): void {
                    $record->update([
                        'status' => $record->status === 'active' ? 'suspended' : 'active',
                    ]);
                    Notification::make()
                        ->title(__('admin/shops.actions.status_updated'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
