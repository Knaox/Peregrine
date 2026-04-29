<?php

namespace App\Filament\Pages\Settings\Sections;

use App\Filament\Pages\Settings\CloudflareIps;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class NetworkSection
{
    public static function make(): Section
    {
        return Section::make('Trusted Proxies')
            ->description(
                'IPs or CIDR ranges allowed to set X-Forwarded-* headers. Set this when '
                . 'Peregrine sits behind a reverse proxy (Nginx Proxy Manager, Traefik, '
                . 'Cloudflare, …). Leave empty to trust no proxy. Stored in DB (table '
                . '`settings`) so a Docker stack rebuild does not reset it ; applies on '
                . 'the next request, no container restart needed.'
            )
            ->icon('heroicon-o-shield-check')
            ->headerActions([
                Action::make('clearTrustedProxies')
                    ->label('Clear')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->link()
                    ->action(fn (Set $set) => $set('trusted_proxies', [])),
                Action::make('setCloudflareIps')
                    ->label('Set to Cloudflare IPs')
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
            ])
            ->schema([
                TagsInput::make('trusted_proxies')
                    ->label('')
                    ->placeholder('New IP or IP Range')
                    ->reorderable()
                    ->helperText(
                        'Examples: 192.168.80.1 (single host), 172.16.0.0/12 (CIDR range), '
                        . '2400:cb00::/32 (IPv6 CIDR). Tip: click "Set to Cloudflare IPs" '
                        . 'to seed the list with all of Cloudflare\'s edge IPs, then add '
                        . 'your own private proxy IP.'
                    ),
            ])->columns(1);
    }
}
