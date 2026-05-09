<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Models\ServerConfiguration;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Relation manager attaching `ServerConfiguration` rows to a `Shop` via
 * the `shop_server_configuration` pivot. Each pivot row carries
 * shop-specific metadata (external id, visibility, sort order) so the
 * same configuration can be sold by multiple shops with their own
 * naming conventions.
 */
class ServerConfigurationsRelationManager extends RelationManager
{
    protected static string $relationship = 'serverConfigurations';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('internal_name')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('internal_name')->searchable(),
                Tables\Columns\TextColumn::make('pivot.shop_external_id')
                    ->label(__('admin/shops.fields.shop_external_id'))
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('pivot.is_visible')
                    ->boolean()
                    ->label(__('admin/shops.fields.visible')),
                Tables\Columns\TextColumn::make('pivot.sort_order')
                    ->label(__('admin/shops.fields.sort_order'))
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['internal_name'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('shop_external_id')
                            ->label(__('admin/shops.fields.shop_external_id'))
                            ->maxLength(255),
                        Toggle::make('is_visible')->default(true),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ]),
            ])
            ->recordActions([
                DetachAction::make()->requiresConfirmation(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
