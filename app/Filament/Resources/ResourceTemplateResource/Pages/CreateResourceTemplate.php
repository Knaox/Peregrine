<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResourceTemplateResource\Pages;

use App\Filament\Resources\ResourceTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResourceTemplate extends CreateRecord
{
    protected static string $resource = ResourceTemplateResource::class;
}
