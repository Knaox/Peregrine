<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDeleteAction;
use App\Filament\Resources\NodeResource\Pages;
use App\Models\Node;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.pelican');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.nodes.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.nodes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.nodes.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pelican_node_id')
                    ->label('Pelican Node ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fqdn')
                    ->label('FQDN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('memory')
                    ->label('Memory')
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' MB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('disk')
                    ->label('Disk')
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' MB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->options(fn () => Node::query()
                        ->whereNotNull('location')
                        ->distinct()
                        ->pluck('location', 'location')
                        ->all()),
            ])
            ->recordActions([
                ResourceDeleteAction::row(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-cpu-chip')
            ->emptyStateHeading(__('admin.resources.nodes.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.nodes'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNodes::route('/'),
        ];
    }
}
