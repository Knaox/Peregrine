<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Settings\Sections\BrandingSection;
use App\Filament\Pages\Settings\Sections\DeveloperSection;
use App\Filament\Pages\Settings\Sections\GeneralSection;
use App\Filament\Pages\Settings\Sections\NetworkSection;
use App\Filament\Pages\Settings\Sections\PelicanSection;
use App\Filament\Pages\Settings\Sections\SmtpSection;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Settings page form schema, organised in top-level tabs so each concern
 * (identity, visuals, infra, mail, network, advanced) gets its own page-like
 * surface instead of one long scroll. Pattern inspired by Paymenter / Pelican
 * admin UX — every panel lives behind a single icon-prefixed tab.
 *
 * Each section's body lives in its own file under `Sections/` so this
 * schema stays under the 300-line plafond CLAUDE.md.
 */
final class SettingsFormSchema
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function tabs(): array
    {
        return [
            Tabs::make('settings_tabs')
                ->tabs([
                    Tab::make(__('admin.settings_form.tabs.general'))
                        ->icon('heroicon-o-home')
                        ->schema([self::general()]),
                    Tab::make(__('admin.settings_form.tabs.branding'))
                        ->icon('heroicon-o-paint-brush')
                        ->schema([self::branding()]),
                    Tab::make(__('admin.settings_form.tabs.pelican'))
                        ->icon('heroicon-o-globe-alt')
                        ->schema([self::pelican()]),
                    Tab::make(__('admin.settings_form.tabs.mail'))
                        ->icon('heroicon-o-envelope')
                        ->schema([self::smtp()]),
                    Tab::make(__('admin.settings_form.tabs.network'))
                        ->icon('heroicon-o-shield-check')
                        ->schema([self::network()]),
                    Tab::make(__('admin.settings_form.tabs.advanced'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema([self::developer()]),
                ])
                ->persistTabInQueryString('settingsTab'),
        ];
    }

    public static function general(): Section
    {
        return GeneralSection::make(self::timezoneOptions());
    }

    public static function branding(): Section
    {
        return BrandingSection::make();
    }

    public static function pelican(): Section
    {
        return PelicanSection::make();
    }

    public static function smtp(): Section
    {
        return SmtpSection::make();
    }

    public static function network(): Section
    {
        return NetworkSection::make();
    }

    public static function developer(): Section
    {
        return DeveloperSection::make();
    }

    /**
     * IANA timezone list, with a few shortcuts pinned at the top so the
     * common picks are 1 click away.
     *
     * @return array<string, string>
     */
    private static function timezoneOptions(): array
    {
        $pinned = [
            'UTC' => 'UTC (Coordinated Universal Time)',
            'Europe/Paris' => 'Europe/Paris (CET/CEST)',
            'Europe/London' => 'Europe/London (GMT/BST)',
            'America/New_York' => 'America/New_York (EST/EDT)',
            'America/Los_Angeles' => 'America/Los_Angeles (PST/PDT)',
        ];
        $rest = [];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            if (! isset($pinned[$tz])) {
                $rest[$tz] = $tz;
            }
        }
        return $pinned + $rest;
    }
}
