<?php

namespace Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource;

class EditEggConfigFile extends EditRecord
{
    protected static string $resource = EggConfigFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
