<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ResourceDeleteAction;
use App\Filament\Resources\UserResource\Pages;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.users.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.users.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $isShopStripe = app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();

        $integrationFields = [
            Section::make(__('admin.tabs.pelican_link'))
                ->icon('heroicon-o-link')
                ->columns(2)
                ->schema([
                    TextInput::make('pelican_user_id')
                        ->label('Pelican User ID')
                        ->helperText('Manual override — changing this re-maps the user to a different Pelican account without touching Pelican.')
                        ->numeric()
                        ->nullable(),
                ]),
        ];

        if ($isShopStripe) {
            $integrationFields[] = Section::make(__('admin.tabs.stripe_link'))
                ->icon('heroicon-o-credit-card')
                ->columns(2)
                ->schema([
                    TextInput::make('stripe_customer_id')
                        ->label('Stripe Customer ID')
                        ->maxLength(255)
                        ->helperText('Set automatically by the first Stripe checkout. Edit only to fix a mismatch.'),
                ]);
        }

        $integrationFields[] = Section::make(__('admin.tabs.oauth_link'))
            ->icon('heroicon-o-identification')
            ->description(__('admin.common.system_managed'))
            ->columns(2)
            ->collapsed()
            ->schema([
                TextInput::make('oauth_provider')
                    ->label('OAuth Provider')
                    ->disabled()
                    ->placeholder('—'),
                TextInput::make('oauth_id')
                    ->label('OAuth ID')
                    ->disabled()
                    ->placeholder('—'),
            ]);

        return $schema
            ->schema([
                Section::make('Identity')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon('heroicon-o-envelope'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255)
                            ->minLength(8)
                            ->visibleOn('create')
                            ->helperText('Minimum 8 characters. Hashed with bcrypt before storage.'),
                        Select::make('locale')
                            ->options([
                                'en' => 'English',
                                'fr' => 'Français',
                            ])
                            ->default('en')
                            ->native(false),
                        Toggle::make('is_admin')
                            ->label('Administrator')
                            ->helperText('Grants access to /admin and elevates Gate::before whitelist (Server only).')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),

                ...$integrationFields,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Lang')
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'EN'))
                    ->color('gray')
                    ->size('xs')
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
                ActionGroup::make([
                    EditAction::make(),
                    self::linkToPelicanAction(),
                    self::changePasswordAction(),
                    ResourceDeleteAction::row(),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size(Size::Small)
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ResourceDeleteAction::bulk(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading(__('admin.resources.users.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.users'));
    }

    private static function linkToPelicanAction(): Action
    {
        return Action::make('link_to_pelican')
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
                    });
    }

    private static function changePasswordAction(): Action
    {
        return Action::make('change_password')
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
                    });
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
