<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Provision a matching Pelican account in the background. The
        // queue worker handles retries on Pelican outages; an admin who
        // needs the link before queue processing can still hit the
        // per-row "Link to Pelican" action on the index page.
        LinkPelicanAccountJob::dispatch($this->record->id, 'admin');
    }
}
