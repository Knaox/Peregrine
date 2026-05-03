<?php

namespace App\Filament\Pages\Settings\Sections;

use App\Filament\Pages\Settings\CloudflareIps;
use App\Filament\Pages\Settings\DockerPrivateRanges;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
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
                    ->label('')
                    ->placeholder(__('admin.settings_form.network.placeholder'))
                    ->reorderable()
                    ->helperText(__('admin.settings_form.network.helper')),
            ])->columns(1);
    }
}
