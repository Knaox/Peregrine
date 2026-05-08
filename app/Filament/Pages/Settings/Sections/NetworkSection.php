<?php

namespace App\Filament\Pages\Settings\Sections;

use App\Filament\Pages\Settings\CloudflareIps;
use App\Filament\Pages\Settings\DockerPrivateRanges;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class NetworkSection
{
    public static function make(): Section
    {
        return Section::make(__('admin.settings_form.network.section'))
            ->description(__('admin.settings_form.network.description'))
            ->icon('heroicon-o-shield-check')
            ->headerActions([
                Action::make('clearTrustedProxies')
                    ->label(__('admin.settings_form.network.clear'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->link()
                    ->action(fn (Set $set) => $set('trusted_proxies', [])),
                Action::make('setCloudflareIps')
                    ->label(__('admin.settings_form.network.cloudflare'))
                    ->icon('heroicon-o-cloud')
                    ->color('primary')
                    ->link()
                    ->action(function (Get $get, Set $set): void {
                        // Merge current entries (so the operator's private
                        // proxy IP is preserved) with Cloudflare's official
                        // ranges. Dedupe to keep the chip list tidy.
                        $current = $get('trusted_proxies') ?? [];
                        $merged = array_values(array_unique([
                            ...(is_array($current) ? $current : []),
                            ...CloudflareIps::all(),
                        ]));
                        $set('trusted_proxies', $merged);
                    }),
                Action::make('setDockerRanges')
                    ->label(__('admin.settings_form.network.docker'))
                    ->icon('heroicon-o-cube')
                    ->color('primary')
                    ->link()
                    ->action(function (Get $get, Set $set): void {
                        // Add the RFC 1918 ranges Docker uses for its bridge
                        // networks. Without these, isFromTrustedProxy() fails
                        // because REMOTE_ADDR is the docker gateway IP, not
                        // the LAN IP of the reverse proxy.
                        $current = $get('trusted_proxies') ?? [];
                        $merged = array_values(array_unique([
                            ...(is_array($current) ? $current : []),
                            ...DockerPrivateRanges::all(),
                        ]));
                        $set('trusted_proxies', $merged);
                    }),
            ])
            ->schema([
                TagsInput::make('trusted_proxies')
                    ->label(__('admin.settings_form.network.trusted_proxies_label'))
                    ->placeholder(__('admin.settings_form.network.placeholder'))
                    ->reorderable()
                    ->helperText(__('admin.settings_form.network.helper')),
                // /broadcasting/auth rate cap. Echo POSTs once per
                // private channel subscription ; on a fresh tab open
                // an admin sees server + user + admin-mirror = 3 / load
                // baseline, but a noisy reconnect (network blip,
                // sleep / wake) can re-auth every channel in a burst.
                // Default 240 / min (4 / sec) is generous for any
                // single user ; the operator can lift further if
                // they have many users behind one Cloudflare IP.
                TextInput::make('broadcasting_auth_rate_limit_per_minute')
                    ->label(__('admin.settings_form.network.broadcasting_auth_label'))
                    ->helperText(__('admin.settings_form.network.broadcasting_auth_helper'))
                    ->numeric()
                    ->minValue(30)
                    ->maxValue(10000)
                    ->step(10)
                    ->default(240)
                    ->required(),
                // Pelican-proxy cap (websocket creds + runtime resources).
                // Set HIGH (default 600/min/user) on purpose : the SPA's
                // useWingsWebSocket reconnect-on-network-blip can spike
                // these endpoints, and a too-low cap would force "give up"
                // before the ws actually recovers. Lower this only if you
                // run a many-tenant Peregrine and want to protect Pelican
                // from a misbehaving client.
                TextInput::make('pelican_proxy_rate_limit_per_minute')
                    ->label(__('admin.settings_form.network.pelican_proxy_label'))
                    ->helperText(__('admin.settings_form.network.pelican_proxy_helper'))
                    ->numeric()
                    ->minValue(60)
                    ->maxValue(100000)
                    ->step(100)
                    ->default(6000)
                    ->required(),
            ])->columns(1);
    }
}
