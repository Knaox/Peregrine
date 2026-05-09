<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResourceTemplateResource\Pages;

use App\Filament\Resources\ResourceTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResourceTemplate extends EditRecord
{
    protected static string $resource = ResourceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
