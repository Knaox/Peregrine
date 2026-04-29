<?php

namespace App\Filament\Resources\PelicanServerTransferResource\Pages;

use App\Filament\Resources\PelicanServerTransferResource;
use Filament\Resources\Pages\ListRecords;

class ListPelicanServerTransfers extends ListRecords
{
    protected static string $resource = PelicanServerTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
