<?php

namespace App\Filament\Pages\Settings;

/**
 * Static list of Cloudflare's published proxy IP ranges (v4 + v6).
 *
 * Used by the "Set to Cloudflare IPs" admin action on the Trusted Proxies
 * field. Refresh occasionally from https://www.cloudflare.com/ips/ — the
 * list changes rarely (last verified 2026-04-25).
 *
 * Kept as a frozen list rather than fetched live: an admin clicking the
 * button must not require Peregrine to make an outbound HTTP call to
 * cloudflare.com (slow, fails on air-gapped installs, fails in CI).
 */
final class CloudflareIps
{
    /**
     * @return array<int, string>
     */
    public static function ipv4(): array
    {
        return [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function ipv6(): array
    {
        return [
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [...self::ipv4(), ...self::ipv6()];
    }
}
