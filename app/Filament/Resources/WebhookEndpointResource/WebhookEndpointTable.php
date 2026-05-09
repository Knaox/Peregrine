<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookEndpointResource;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

final class WebhookEndpointTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('url')->limit(60)->copyable(),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'success' => 'active',
                    'warning' => 'paused',
                    'gray' => 'disabled',
                ]),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label(__('admin/webhook_endpoints.fields.consecutive_failures'))
                    ->badge()
                    ->color(fn ($state) => $state >= 3 ? 'danger' : ($state >= 1 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('last_delivery_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'active',
                    'paused' => 'paused',
                    'disabled' => 'disabled',
                ]),
                Tables\Filters\SelectFilter::make('shop_id')->relationship('shop', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
