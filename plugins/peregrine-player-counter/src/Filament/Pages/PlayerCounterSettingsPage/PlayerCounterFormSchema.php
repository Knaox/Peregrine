<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Filament\Pages\PlayerCounterSettingsPage;

use App\Models\Egg;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;

/**
 * Form schema for the `/admin/player-counter-settings` page. Kept as a sibling
 * of the page (300-line rule). One Connection section: enable toggle, sidecar
 * URL, and an optional shared token with an inline generator.
 */
final class PlayerCounterFormSchema
{
    private const NS = 'peregrine-player-counter::messages.settings.';

    /** @return array<int, mixed> */
    public static function sections(): array
    {
        return [self::connectionSection(), self::visibilitySection()];
    }

    private static function visibilitySection(): Section
    {
        return Section::make(__(self::NS.'section_visibility'))
            ->schema([
                Select::make('egg_whitelist')
                    ->label(__(self::NS.'egg_whitelist'))
                    ->helperText(__(self::NS.'egg_whitelist_help'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Egg::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->columns(1);
    }

    private static function connectionSection(): Section
    {
        return Section::make(__(self::NS.'section_connection'))
            ->schema([
                Toggle::make('enabled')
                    ->label(__(self::NS.'enabled'))
                    ->helperText(__(self::NS.'enabled_help')),

                TextInput::make('sidecar_url')
                    ->label(__(self::NS.'sidecar_url'))
                    ->url()
                    ->maxLength(512)
                    ->placeholder('http://127.0.0.1:9899')
                    ->helperText(__(self::NS.'sidecar_url_help')),

                TextInput::make('sidecar_token')
                    ->label(__(self::NS.'sidecar_token'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__(self::NS.'sidecar_token_help'))
                    ->suffixAction(
                        Action::make('regenerate')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__(self::NS.'regenerate'))
                            ->action(function (Set $set): void {
                                $set('sidecar_token', PlayerCounterSettings::generateToken());
                                Notification::make()->title(__(self::NS.'regenerated'))->warning()->send();
                            }),
                    ),
            ])
            ->columns(1);
    }
}
