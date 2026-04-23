<?php

namespace App\Filament\Resources\ServerPlanResource\Pages;

use App\Filament\Resources\ServerPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListServerPlans extends ListRecords
{
    protected static string $resource = ServerPlanResource::class;

    protected function getHeaderActions(): array
    {
        // Plans are pushed by the Shop via Bridge API — never created from
        // the Peregrine admin. No CreateAction here.
        return [];
    }
}
