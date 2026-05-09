<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Actions\Shops\GenerateShopApiKeyAction;
use App\Actions\Shops\RevokeShopApiKeyAction;
use App\Models\ShopApiKey;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Relation manager for `ShopApiKey` rows owned by the parent `Shop`.
 *
 * `Generate` is a header action (not the standard CreateAction) so we can
 * intercept the form submit and display the resulting plaintext token in
 * a one-shot Notification — we never persist the plaintext, only the
 * SHA-256 hash. The admin must copy the token before dismissing.
 */
class ApiKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'apiKeys';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('key_prefix')
                    ->label('Prefix')
                    ->formatStateUsing(fn (ShopApiKey $r) => $r->key_prefix.'…'.$r->key_last4)
                    ->copyable(),
                Tables\Columns\TextColumn::make('abilities')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '—')
                    ->limit(40),
                Tables\Columns\TextColumn::make('last_used_at')->dateTime()->placeholder('never'),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('—'),
                Tables\Columns\IconColumn::make('revoked_at')
                    ->label('Revoked')
                    ->boolean()
                    ->getStateUsing(fn (ShopApiKey $r) => $r->revoked_at !== null),
            ])
            ->headerActions([
                Action::make('generate')
                    ->label(__('admin/shop_api_keys.actions.generate'))
                    ->icon('heroicon-o-key')
                    ->form([
                        TextInput::make('label')->required()->maxLength(255),
                        Select::make('abilities')
                            ->multiple()
                            ->options([
                                'configurations:read' => 'configurations:read',
                                'orders:read' => 'orders:read',
                                'webhooks:read' => 'webhooks:read',
                                'webhooks:write' => 'webhooks:write',
                            ]),
                        DateTimePicker::make('expires_at')->nullable(),
                        Select::make('env')
                            ->options(['live' => 'live', 'test' => 'test'])
                            ->default('live')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $shop = $this->getOwnerRecord();
                        $result = (new GenerateShopApiKeyAction())(
                            $shop,
                            label: $data['label'],
                            abilities: $data['abilities'] ?? [],
                            expiresAt: $data['expires_at'] ?? null,
                            env: $data['env'] ?? 'live',
                        );
                        Notification::make()
                            ->title(__('admin/shop_api_keys.actions.generated_title'))
                            ->body(__('admin/shop_api_keys.actions.generated_body', ['token' => $result['plaintext']]))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('admin/shop_api_keys.actions.revoke'))
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (ShopApiKey $r) => $r->revoked_at === null)
                    ->requiresConfirmation()
                    ->action(function (ShopApiKey $record): void {
                        (new RevokeShopApiKeyAction())($record);
                        Notification::make()
                            ->title(__('admin/shop_api_keys.actions.revoked'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
