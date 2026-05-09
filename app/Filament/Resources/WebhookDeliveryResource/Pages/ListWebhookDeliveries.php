<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookDeliveryResource\Pages;

use App\Filament\Resources\WebhookDeliveryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListWebhookDeliveries extends ListRecords
{
    protected static string $resource = WebhookDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docs')
                ->label(__('admin/webhook_deliveries.actions.docs'))
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(fn (): string => url('/docs/standard-webhooks'), shouldOpenInNewTab: true),
        ];
    }
}
