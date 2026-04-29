<?php

namespace App\Filament\Resources\PelicanBackupResource\Pages;

use App\Filament\Resources\PelicanBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListPelicanBackups extends ListRecords
{
    protected static string $resource = PelicanBackupResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
