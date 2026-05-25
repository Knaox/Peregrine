<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Filament\Pages\PmaPluginSettings;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;

/**
 * Form schema for the `/admin/pma-settings` page. Kept as a sibling of the
 * page (300-line rule). Two sections: Connection (enabled, PMA URL, shared
 * secret with inline regenerate, token TTL, auto-select db) and an advanced,
 * collapsed Security section (IP allowlist, per-user rate limit).
 */
final class PmaSettingsFormSchema
{
    private const NS = 'peregrine-phpmyadmin::messages.settings.';

    /** @return array<int, mixed> */
    public static function sections(): array
    {
        return [self::connectionSection(), self::securitySection()];
    }

    private static function connectionSection(): Section
    {
        return Section::make(__(self::NS.'section_connection'))
            ->schema([
                Toggle::make('enabled')
                    ->label(__(self::NS.'enabled'))
                    ->helperText(__(self::NS.'enabled_help')),

                TextInput::make('pma_url')
                    ->label(__(self::NS.'pma_url'))
                    ->url()
                    ->maxLength(512)
                    ->placeholder('https://pma.example.com')
                    ->helperText(__(self::NS.'pma_url_help')),

                TextInput::make('shared_secret')
                    ->label(__(self::NS.'shared_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__(self::NS.'shared_secret_help'))
                    ->suffixAction(
                        Action::make('regenerate')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__(self::NS.'regenerate'))
                            ->requiresConfirmation()
                            ->modalDescription(__(self::NS.'regenerate_warning'))
                            ->action(function (Set $set): void {
                                $set('shared_secret', PmaSettings::generateSecret());
                                Notification::make()
                                    ->title(__(self::NS.'regenerated'))
                                    ->warning()
                                    ->send();
                            }),
                    ),

                TextInput::make('token_ttl')
                    ->label(__(self::NS.'token_ttl'))
                    ->numeric()
                    ->minValue(10)
                    ->maxValue(120)
                    ->default(30)
                    ->helperText(__(self::NS.'token_ttl_help')),

                Toggle::make('auto_login')
                    ->label(__(self::NS.'auto_login'))
                    ->helperText(__(self::NS.'auto_login_help')),

                Toggle::make('auto_select_db')
                    ->label(__(self::NS.'auto_select_db'))
                    ->helperText(__(self::NS.'auto_select_db_help')),

                TextInput::make('pma_server_index')
                    ->label(__(self::NS.'server_index'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->placeholder('2')
                    ->helperText(__(self::NS.'server_index_help')),
            ])
            ->columns(1);
    }

    private static function securitySection(): Section
    {
        return Section::make(__(self::NS.'section_security'))
            ->description(__(self::NS.'section_security_desc'))
            ->collapsible()
            ->collapsed()
            ->schema([
                Textarea::make('ip_allowlist')
                    ->label(__(self::NS.'ip_allowlist'))
                    ->rows(4)
                    ->helperText(__(self::NS.'ip_allowlist_help')),

                TextInput::make('rate_limit_per_user')
                    ->label(__(self::NS.'rate_limit'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->default(20)
                    ->helperText(__(self::NS.'rate_limit_help')),
            ])
            ->columns(1);
    }
}
