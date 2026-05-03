<?php

namespace App\Filament\Pages\Settings;

/**
 * RFC 1918 private ranges Docker uses for its bridge networks.
 *
 * In a typical Docker deployment the reverse proxy (NPM, Traefik, …) talks
 * to the Peregrine container through a bridge network, so REMOTE_ADDR seen
 * by Peregrine is the Docker gateway IP (e.g. 172.17.0.1, 172.20.0.1, …),
 * NOT the LAN IP of the proxy host. Symfony's isFromTrustedProxy() then
 * returns false and X-Forwarded-Proto is ignored, breaking isSecure() and
 * Livewire signed-URL uploads.
 *
 * Adding these three ranges covers every default Docker network as well as
 * any custom subnet an operator might pick from RFC 1918.
 */
final class DockerPrivateRanges
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            '172.16.0.0/12',  // Default Docker bridge networks (172.17–172.31)
            '192.168.0.0/16', // Common LAN range
            '10.0.0.0/8',     // Common Docker overlay / Swarm range
        ];
    }
}
