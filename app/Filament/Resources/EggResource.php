<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EggResource\Pages;
use App\Models\Egg;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class EggResource extends Resource
{
    protected static ?string $model = Egg::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Pelican';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->disabled(),
                Textarea::make('description')
                    ->label('Description')
                    ->disabled(),
                TextInput::make('docker_image')
                    ->label('Docker Image')
                    ->disabled(),
                FileUpload::make('banner_image')
                    ->label('Banner Image')
                    ->image()
                    ->directory('eggs/banners')
                    ->disk('public')
                    ->maxSize(2048)
                    ->helperText('Recommended: 800x450px (16:9). Max 2MB.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pelican_egg_id')
                    ->label('Pelican Egg ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nest.name')
                    ->label('Nest')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('docker_image')
                    ->label('Docker Image')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEggs::route('/'),
            'edit' => Pages\EditEgg::route('/{record}/edit'),
        ];
    }
}
