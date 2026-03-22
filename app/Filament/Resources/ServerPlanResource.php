<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerPlanResource\Pages;
use App\Models\ServerPlan;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ServerPlanResource extends Resource
{
    protected static ?string $model = ServerPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Plans';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Plan Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('stripe_price_id')
                            ->label('Stripe Price ID')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                Section::make('Resources')
                    ->schema([
                        TextInput::make('ram')
                            ->label('RAM')
                            ->numeric()
                            ->required()
                            ->suffix('MB'),
                        TextInput::make('cpu')
                            ->label('CPU')
                            ->numeric()
                            ->required()
                            ->suffix('%'),
                        TextInput::make('disk')
                            ->label('Disk')
                            ->numeric()
                            ->required()
                            ->suffix('MB'),
                    ])->columns(3),

                Section::make('Associations')
                    ->schema([
                        Select::make('egg_id')
                            ->relationship('egg', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('nest_id')
                            ->relationship('nest', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('node_id')
                            ->relationship('node', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stripe_price_id')
                    ->label('Stripe Price')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('egg.name')
                    ->label('Egg')
                    ->sortable(),
                Tables\Columns\TextColumn::make('node.name')
                    ->label('Node')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ram')
                    ->label('RAM')
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' MB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cpu')
                    ->label('CPU')
                    ->formatStateUsing(fn (int $state): string => $state . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('disk')
                    ->label('Disk')
                    ->formatStateUsing(fn (int $state): string => number_format($state) . ' MB')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerPlans::route('/'),
            'create' => Pages\CreateServerPlan::route('/create'),
            'edit' => Pages\EditServerPlan::route('/{record}/edit'),
        ];
    }
}
