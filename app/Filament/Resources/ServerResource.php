<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Filament\Resources\ServerResource\ServerFormSchemaBuilder;
use App\Filament\Resources\ServerResource\ServerTableSchemaBuilder;
use App\Models\Server;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ServerFormSchemaBuilder::build($schema);
    }

    public static function table(Table $table): Table
    {
        return ServerTableSchemaBuilder::build($table);
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
