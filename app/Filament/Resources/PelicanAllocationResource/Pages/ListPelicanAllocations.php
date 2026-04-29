<?php

namespace App\Filament\Resources\PelicanAllocationResource\Pages;

use App\Filament\Resources\PelicanAllocationResource;
use Filament\Resources\Pages\ListRecords;

class ListPelicanAllocations extends ListRecords
{
    protected static string $resource = PelicanAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
