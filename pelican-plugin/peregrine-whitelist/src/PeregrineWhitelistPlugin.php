<?php

namespace Pelican\PeregrineWhitelist;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Section;

class PeregrineWhitelistPlugin implements Plugin, HasPluginSettings
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'peregrine-whitelist';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function getSettingsForm(): array
    {
        return [
            Section::make(trans('peregrine-whitelist::peregrine-whitelist.settings.title'))
                ->description(trans('peregrine-whitelist::peregrine-whitelist.settings.description'))
                ->schema([
                    TextInput::make('PEREGRINE_WHITELIST_IPS')
                        ->label(trans('peregrine-whitelist::peregrine-whitelist.settings.ips'))
                        ->helperText(trans('peregrine-whitelist::peregrine-whitelist.settings.ips_help'))
                        ->default(fn () => implode(',', (array) config('peregrine-whitelist.ips', []))),
                    TextInput::make('PEREGRINE_WHITELIST_HOSTS')
                        ->label(trans('peregrine-whitelist::peregrine-whitelist.settings.hosts'))
                        ->helperText(trans('peregrine-whitelist::peregrine-whitelist.settings.hosts_help'))
                        ->default(fn () => implode(',', (array) config('peregrine-whitelist.hostnames', []))),
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PEREGRINE_WHITELIST_IPS' => $data['PEREGRINE_WHITELIST_IPS'] ?? '',
            'PEREGRINE_WHITELIST_HOSTS' => $data['PEREGRINE_WHITELIST_HOSTS'] ?? '',
        ]);

        Notification::make()
            ->title(trans('peregrine-whitelist::peregrine-whitelist.notifications.saved'))
            ->success()
            ->send();
    }
}
