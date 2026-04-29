<?php

namespace App\Filament\Pages\AuthSettings;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Form schema for the "Auth & Security" admin page.
 *
 * Top-level Tabs split the page by concern (General / Shop / Paymenter /
 * Social / Safety) so admins land directly on what they need to edit.
 *
 * Each OAuth provider exposes ALL of its endpoint URLs as editable fields,
 * including the Redirect URI — earlier versions left it read-only, which
 * trapped operators whose installer wrote the wrong host (e.g. an internal
 * Docker IP captured before a reverse proxy was put in front).
 */
final class AuthSettingsFormSchema
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function tabs(string $shopRedirect, string $paymenterRedirect, string $googleRedirect, string $discordRedirect, string $linkedinRedirect): array
    {
        return [
            Tabs::make('auth_settings_tabs')
                ->tabs([
                    Tab::make(__('admin.auth_form.tabs.general'))
                        ->icon('heroicon-o-home')
                        ->schema([self::general(), self::twoFactor()]),
                    Tab::make(__('admin.auth_form.tabs.shop'))
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([self::shop($shopRedirect)]),
                    Tab::make(__('admin.auth_form.tabs.paymenter'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([self::paymenter($paymenterRedirect)]),
                    Tab::make(__('admin.auth_form.tabs.social'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            self::socialProvider('google', 'Google', 'heroicon-o-globe-alt', $googleRedirect),
                            self::socialProvider('discord', 'Discord', 'heroicon-o-chat-bubble-left-right', $discordRedirect),
                            self::socialProvider('linkedin', 'LinkedIn', 'heroicon-o-briefcase', $linkedinRedirect),
                        ]),
                    Tab::make(__('admin.auth_form.tabs.safety'))
                        ->icon('heroicon-o-exclamation-triangle')
                        ->schema([self::safety()]),
                ])
                ->persistTabInQueryString('authTab'),
        ];
    }

    public static function general(): Section
    {
        return Section::make(__('admin.auth_form.local.section'))
            ->description(__('admin.auth_form.local.description'))
            ->icon('heroicon-o-key')
            ->schema([
                Toggle::make('auth_local_enabled')
                    ->label(__('admin.auth_form.local.enabled'))
                    ->helperText(__('admin.auth_form.local.enabled_helper')),
                Toggle::make('auth_local_registration_enabled')
                    ->label(__('admin.auth_form.local.registration'))
                    ->helperText(__('admin.auth_form.local.registration_helper')),
            ]);
    }

    public static function twoFactor(): Section
    {
        return Section::make(__('admin.auth_form.two_factor.section'))
            ->description(__('admin.auth_form.two_factor.description'))
            ->icon('heroicon-o-shield-check')
            ->schema([
                Toggle::make('auth_2fa_enabled')
                    ->label(__('admin.auth_form.two_factor.enabled'))
                    ->helperText(__('admin.auth_form.two_factor.enabled_helper')),
                Toggle::make('auth_2fa_required_admins')
                    ->label(__('admin.auth_form.two_factor.required_admins'))
                    ->helperText(__('admin.auth_form.two_factor.required_admins_helper')),
            ]);
    }

    public static function shop(string $redirectUri): Section
    {
        return Sections\ShopProviderSection::make($redirectUri);
    }

    /**
     * Reusable builder for Google / Discord / LinkedIn — same shape with
     * an editable redirect URI and a reset-to-default action.
     */
    public static function socialProvider(string $providerId, string $label, string $icon, string $redirectUri): Section
    {
        return Section::make($label)
            ->icon($icon)
            ->collapsible()
            ->collapsed()
            ->schema([
                Toggle::make("auth_providers_{$providerId}_enabled")->label(__('admin.auth_form.social.enable', ['provider' => $label])),

                TextInput::make("auth_providers_{$providerId}_client_id")
                    ->label(__('admin.auth_form.social.client_id'))
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.social.client_id_helper', ['provider' => $label])),

                TextInput::make("auth_providers_{$providerId}_client_secret")
                    ->label(__('admin.auth_form.social.client_secret'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->helperText(__('admin.auth_form.social.client_secret_helper')),

                TextInput::make("auth_providers_{$providerId}_redirect_uri")
                    ->label(__('admin.auth_form.social.redirect'))
                    ->url()
                    ->maxLength(255)
                    ->placeholder($redirectUri)
                    ->helperText(__('admin.auth_form.social.redirect_helper', ['provider' => $label]))
                    ->suffixAction(
                        Action::make("reset{$providerId}Redirect")
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip(__('admin.auth_form.social.reset_tooltip'))
                            ->color('gray')
                            ->action(function (Set $set) use ($providerId, $redirectUri): void {
                                $set("auth_providers_{$providerId}_redirect_uri", $redirectUri);
                            }),
                    ),
            ]);
    }

    public static function paymenter(string $redirectUri): Section
    {
        return Sections\PaymenterProviderSection::make($redirectUri);
    }

    public static function safety(): Section
    {
        return Section::make(__('admin.auth_form.safety.section'))
            ->description(__('admin.auth_form.safety.description'))
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                Toggle::make('acknowledge_disable_risk')
                    ->label(__('admin.auth_form.safety.ack'))
                    ->helperText(__('admin.auth_form.safety.ack_helper')),
            ]);
    }
}
