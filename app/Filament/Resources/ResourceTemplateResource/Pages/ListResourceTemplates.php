<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResourceTemplateResource\Pages;

use App\Filament\Resources\ResourceTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceTemplates extends ListRecords
{
    protected static string $resource = ResourceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
