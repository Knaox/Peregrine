<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docs')
                ->label(__('admin/shops.actions.docs'))
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(fn (): string => url('/docs/shops'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
