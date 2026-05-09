<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ShopFormSchema
{
    /** @return array<int, mixed> */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin/shops.fields.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->label(__('admin/shops.fields.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->maxLength(255)
                ->helperText(__('admin/shops.helpers.slug')),

            TextInput::make('domain')
                ->label(__('admin/shops.fields.domain'))
                ->placeholder('shop.example.com')
                ->maxLength(255)
                ->helperText(__('admin/shops.helpers.domain')),

            Select::make('status')
                ->label(__('admin/shops.fields.status'))
                ->options([
                    'active' => __('admin/shops.status.active'),
                    'suspended' => __('admin/shops.status.suspended'),
                ])
                ->default('active')
                ->required(),

            Textarea::make('metadata')
                ->label(__('admin/shops.fields.metadata'))
                ->helperText(__('admin/shops.helpers.metadata'))
                ->rows(3)
                ->columnSpanFull()
                ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)
                ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null),
        ];
    }
}
