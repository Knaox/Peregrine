<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookEndpointResource\Pages;

use App\Filament\Resources\WebhookEndpointResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebhookEndpoints extends ListRecords
{
    protected static string $resource = WebhookEndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docs')
                ->label(__('admin/webhook_endpoints.actions.docs'))
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(fn (): string => url('/docs/standard-webhooks'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
