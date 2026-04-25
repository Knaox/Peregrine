<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDeleteAction;
use App\Filament\Resources\UserResource\Pages;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isShopStripe = app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();

        return $schema
            ->schema([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->visibleOn('create'),
                        Select::make('locale')
                            ->options([
                                'en' => 'English',
                                'fr' => 'Français',
                            ])
                            ->default('en'),
                        Toggle::make('is_admin')
                            ->label('Administrator')
                            ->default(false),
                    ])->columns(2),

                Section::make('External Integrations')
                    ->schema(array_filter([
                        TextInput::make('pelican_user_id')
                            ->label('Pelican User ID')
                            ->helperText('Manual override — changing this re-maps the user to a different Pelican account without touching Pelican.')
                            ->numeric()
                            ->nullable(),
                        // Stripe Customer ID only relevant in Shop+Stripe mode.
                        $isShopStripe
                            ? TextInput::make('stripe_customer_id')
                                ->label('Stripe Customer ID')
                                ->maxLength(255)
                            : null,
                        TextInput::make('oauth_provider')
                            ->label('OAuth Provider')
                            ->disabled(),
                        TextInput::make('oauth_id')
                            ->label('OAuth ID')
                            ->disabled(),
                    ]))->columns(2),
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('pelican_user_id')
                    ->label('Pelican')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('warning')
                    ->tooltip(fn (User $record): string => $record->pelican_user_id !== null
                        ? "Linked to Pelican user #{$record->pelican_user_id}"
                        : 'No Pelican account yet — use the Link action to provision one.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('servers_count')
                    ->counts('servers')
                    ->label('Servers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Administrator'),
                Tables\Filters\SelectFilter::make('locale')
                    ->options([
                        'en' => 'English',
                        'fr' => 'Français',
                    ]),
                Tables\Filters\TernaryFilter::make('pelican_user_id')
                    ->label('Synced with Pelican')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('link_to_pelican')
                    ->label('Link to Pelican')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->pelican_user_id === null)
                    ->requiresConfirmation()
                    ->modalHeading('Provision a Pelican account')
                    ->modalDescription('Dispatches a background job that finds the user in Pelican by email, or creates one if missing. Safe to retry — the job is idempotent.')
                    ->action(function (User $record): void {
                        LinkPelicanAccountJob::dispatch($record->id, 'admin-manual');
                        Notification::make()
                            ->title('Link job dispatched')
                            ->body("Background job queued for {$record->email}. Refresh in a few seconds to see the linked status.")
                            ->success()
                            ->send();
                    }),
                Action::make('change_password')
                    ->label('Change password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->schema([
                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password'),
                        Toggle::make('sync_pelican')
                            ->label('Also update on Pelican')
                            ->helperText('If this user is synced, push the new password to the Pelican account as well.')
                            ->default(true),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill(['password' => Hash::make($data['password'])])->save();

                        if (($data['sync_pelican'] ?? false) && $record->pelican_user_id) {
                            try {
                                app(PelicanApplicationService::class)
                                    ->updateUserPassword((int) $record->pelican_user_id, $data['password']);
                            } catch (RequestException $e) {
                                report($e);
                                Notification::make()
                                    ->title('Local password updated, Pelican sync failed')
                                    ->body('The local password was changed but Pelican could not be updated. Check the logs.')
                                    ->warning()
                                    ->send();

                                return;
                            }
                        }

                        Notification::make()
                            ->title('Password changed')
                            ->success()
                            ->send();
                    }),
                ResourceDeleteAction::row(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
