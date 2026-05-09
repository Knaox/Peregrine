<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookDeliveryResource;

use App\Jobs\Webhooks\DispatchWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

final class WebhookDeliveryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('event.event_type')
                    ->label(__('admin/webhook_deliveries.fields.event_type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('endpoint.shop.name')
                    ->label(__('admin/webhook_deliveries.fields.shop'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('endpoint.name')
                    ->label(__('admin/webhook_deliveries.fields.endpoint')),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'gray' => 'pending',
                    'success' => 'success',
                    'warning' => 'failed',
                    'danger' => 'expired',
                ]),
                Tables\Columns\TextColumn::make('attempt_count')
                    ->label(__('admin/webhook_deliveries.fields.attempts')),
                Tables\Columns\TextColumn::make('last_status_code')->placeholder('—'),
                Tables\Columns\TextColumn::make('last_attempted_at')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('next_retry_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'pending',
                    'success' => 'success',
                    'failed' => 'failed',
                    'expired' => 'expired',
                ]),
            ])
            ->recordActions([
                Action::make('replay')
                    ->label(__('admin/webhook_deliveries.actions.replay'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (WebhookDelivery $r) => in_array($r->status, ['failed', 'expired'], true))
                    ->requiresConfirmation()
                    ->action(function (WebhookDelivery $record): void {
                        // Reset status to pending so the dispatcher writes a
                        // fresh attempt row instead of treating this as a
                        // terminal-state retry.
                        $record->forceFill(['status' => 'pending', 'next_retry_at' => null])->save();
                        DispatchWebhookDeliveryJob::dispatch($record->id);
                        Notification::make()
                            ->title(__('admin/webhook_deliveries.actions.replay_dispatched'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
