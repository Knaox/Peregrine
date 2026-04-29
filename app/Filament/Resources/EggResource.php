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
        return 'Servers';
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
                Section::make(__('admin.eggs.sections.metadata'))
                    ->description(__('admin.eggs.sections.metadata_description'))
                    ->icon('heroicon-o-lock-closed')
                    ->collapsed()
                    ->schema([
                        TextInput::make('name')->label(__('admin.fields.name'))->disabled(),
                        Textarea::make('description')->label(__('admin.fields.description'))->disabled()->rows(3),
                        TextInput::make('docker_image')->label(__('admin.fields.docker_image'))->disabled(),
                    ]),
                Section::make(__('admin.eggs.sections.local'))
                    ->description(__('admin.eggs.sections.local_description'))
                    ->icon('heroicon-o-photo')
                    ->schema([
                        FileUpload::make('banner_image')
                            ->label(__('admin.fields.banner_image'))
                            ->image()
                            ->directory('eggs/banners')
                            ->disk('public')
                            ->maxSize(2048)
                            ->helperText(__('admin.eggs.helpers.banner')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.fields.id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('pelican_egg_id')
                    ->label(__('admin.fields.pelican_egg_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nest.name')
                    ->label(__('admin.fields.nest'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('docker_image')
                    ->label(__('admin.fields.docker_image'))
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nest_id')
                    ->label(__('admin.fields.nest'))
                    ->relationship('nest', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ResourceDeleteAction::row(__('admin.eggs.delete.row')),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size(Size::Small)
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(__('admin.eggs.delete.bulk')),
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
