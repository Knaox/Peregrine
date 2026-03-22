<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Server Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'running' => 'Running',
                                'stopped' => 'Stopped',
                                'suspended' => 'Suspended',
                                'terminated' => 'Terminated',
                                'offline' => 'Offline',
                            ])
                            ->required(),
                        TextInput::make('pelican_server_id')
                            ->label('Pelican Server ID')
                            ->numeric(),
                    ])->columns(2),

                Section::make('Configuration')
                    ->schema([
                        Select::make('egg_id')
                            ->relationship('egg', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),

                Section::make('Billing')
                    ->schema([
                        TextInput::make('stripe_subscription_id')
                            ->label('Stripe Subscription ID')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('payment_intent_id')
                            ->label('Payment Intent ID')
                            ->maxLength(255)
                            ->nullable()
                            ->disabled(),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'running' => 'success',
                        'stopped' => 'warning',
                        'suspended', 'terminated' => 'danger',
                        'offline' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('egg.name')
                    ->label('Egg')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pelican_server_id')
                    ->label('Pelican ID')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stripe_subscription_id')
                    ->label('Stripe Sub.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'running' => 'Running',
                        'stopped' => 'Stopped',
                        'suspended' => 'Suspended',
                        'terminated' => 'Terminated',
                        'offline' => 'Offline',
                    ]),
                Tables\Filters\TernaryFilter::make('plan_id')
                    ->label('Has Plan')
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('stripe_subscription_id')
                    ->label('Has Stripe Subscription')
                    ->nullable(),
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
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
