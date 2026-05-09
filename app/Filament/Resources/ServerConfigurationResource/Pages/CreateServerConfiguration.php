<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerConfigurationResource\Pages;

use App\Filament\Resources\ServerConfigurationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServerConfiguration extends CreateRecord
{
    protected static string $resource = ServerConfigurationResource::class;
}
