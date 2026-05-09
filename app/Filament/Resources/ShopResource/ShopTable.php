<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

final class ShopTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('domain')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray' => 'suspended',
                    ]),
                Tables\Columns\TextColumn::make('api_keys_count')
                    ->label(__('admin/shops.fields.api_keys_count'))
                    ->counts('apiKeys')
                    ->sortable(),
                Tables\Columns\TextColumn::make('server_configurations_count')
                    ->label(__('admin/shops.fields.configurations_count'))
                    ->counts('serverConfigurations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => __('admin/shops.status.active'),
                    'suspended' => __('admin/shops.status.suspended'),
                ]),
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
