<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDeleteAction;
use App\Filament\Resources\EggResource\Pages;
use App\Models\Egg;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class EggResource extends Resource
{
    protected static ?string $model = Egg::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.pelican');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.eggs.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.eggs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.eggs.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Egg metadata')
                    ->description(__('admin.common.system_managed') . ' — edit in Pelican to change.')
                    ->icon('heroicon-o-lock-closed')
                    ->collapsed()
                    ->schema([
                        TextInput::make('name')->disabled(),
                        Textarea::make('description')->disabled()->rows(3),
                        TextInput::make('docker_image')->disabled(),
                    ]),
                Section::make('Local presentation')
                    ->description('Override the banner image used in the player UI. Stored locally — Pelican never reads this.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        FileUpload::make('banner_image')
                            ->label('Banner Image')
                            ->image()
                            ->directory('eggs/banners')
                            ->disk('public')
                            ->maxSize(2048)
                            ->helperText('Recommended: 800x450px (16:9). Max 2MB.'),
                    ]),
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
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nest_id')
                    ->label('Nest')
                    ->relationship('nest', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ResourceDeleteAction::row(
                        'Deletes this egg + every server built from it (FK cascade). Pick "Peregrine only" to drop just the local copy.',
                    ),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size(Size::Small)
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(
                        'Each deleted egg cascades to every server using it. Double-check before confirming.',
                    ),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading(__('admin.resources.eggs.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.eggs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEggs::route('/'),
            'edit' => Pages\EditEgg::route('/{record}/edit'),
        ];
    }
}
