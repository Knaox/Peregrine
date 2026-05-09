<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookEndpointResource;

use App\Webhooks\WebhookEventTypes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class WebhookEndpointFormSchema
{
    /** @return array<int, mixed> */
    public static function fields(): array
    {
        $eventOptions = collect(WebhookEventTypes::all())
            ->mapWithKeys(fn (string $type) => [$type => $type])
            ->all();

        return [
            Select::make('shop_id')
                ->label(__('admin/webhook_endpoints.fields.shop'))
                ->relationship('shop', 'name')
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('name')
                ->label(__('admin/webhook_endpoints.fields.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('url')
                ->label(__('admin/webhook_endpoints.fields.url'))
                ->required()
                ->url()
                ->maxLength(1024),

            TextInput::make('signing_secret')
                ->label(__('admin/webhook_endpoints.fields.signing_secret'))
                ->required()
                ->maxLength(255)
                ->revealable()
                ->password()
                ->helperText(__('admin/webhook_endpoints.helpers.signing_secret')),

            Select::make('status')
                ->label(__('admin/webhook_endpoints.fields.status'))
                ->options([
                    'active' => __('admin/webhook_endpoints.status.active'),
                    'paused' => __('admin/webhook_endpoints.status.paused'),
                    'disabled' => __('admin/webhook_endpoints.status.disabled'),
                ])
                ->default('active')
                ->required(),

            Select::make('subscribed_events')
                ->label(__('admin/webhook_endpoints.fields.subscribed_events'))
                ->multiple()
                ->options($eventOptions)
                ->default(array_keys($eventOptions))
                ->required(),

            TextInput::make('max_retries')
                ->label(__('admin/webhook_endpoints.fields.max_retries'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(5)
                ->required(),

            TextInput::make('timeout_seconds')
                ->label(__('admin/webhook_endpoints.fields.timeout_seconds'))
                ->numeric()
                ->minValue(5)
                ->maxValue(120)
                ->default(30)
                ->required(),
        ];
    }
}
