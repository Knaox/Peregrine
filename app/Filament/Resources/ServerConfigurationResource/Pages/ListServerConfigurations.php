<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource\Pages;

use App\Filament\Resources\ServerConfigurationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServerConfigurations extends ListRecords
{
    protected static string $resource = ServerConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
