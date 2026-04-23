<?php

namespace App\Filament\Resources\BridgeSyncLogResource\Pages;

use App\Filament\Resources\BridgeSyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListBridgeSyncLogs extends ListRecords
{
    protected static string $resource = BridgeSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
