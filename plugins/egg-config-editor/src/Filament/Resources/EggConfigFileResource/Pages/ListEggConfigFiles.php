<?php

namespace Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource;

class ListEggConfigFiles extends ListRecords
{
    protected static string $resource = EggConfigFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
