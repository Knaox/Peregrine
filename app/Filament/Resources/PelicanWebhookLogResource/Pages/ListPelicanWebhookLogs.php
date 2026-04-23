<?php

namespace App\Filament\Resources\PelicanWebhookLogResource\Pages;

use App\Filament\Resources\PelicanWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPelicanWebhookLogs extends ListRecords
{
    protected static string $resource = PelicanWebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
