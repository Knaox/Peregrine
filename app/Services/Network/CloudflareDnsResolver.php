<?php

declare(strict_types=1);

namespace App\Services\Network;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a hostname to a public IPv4 address using Cloudflare's public
 * DNS-over-HTTPS resolver (https://cloudflare-dns.com/dns-query).
 *
 * No API key required — this is the public 1.1.1.1 resolver, queried with the
 * JSON transport (`Accept: application/dns-json`). Used by the "IP variable"
 * feature on ServerConfiguration to discover the public IP behind a node FQDN
 * or an allocation alias at provisioning time.
 *
 * Behaviour :
 *  - If the input is already a valid IP literal, it's returned as-is (no call).
 *  - Otherwise the first A (type 1) answer's `data` field is returned.
 *  - Failures (network error, NXDOMAIN, no A record) return null — the caller
 *    decides how to degrade.
 *
 * Successful resolutions are cached briefly so a node FQDN that provisions many
 * servers doesn't hit the resolver every time. Failures are NOT cached (a
 * `null` result is indistinguishable from a cache miss in Laravel's cache), so
 * a transient outage is retried on the next provision rather than pinned.
 */
class CloudflareDnsResolver
{
    private const ENDPOINT = 'https://cloudflare-dns.com/dns-query';

    private const CACHE_TTL = 300; // 5 minutes

    /** Resource-record type for an IPv4 address in the DoH JSON transport. */
    private const DNS_TYPE_A = 1;

    public function resolve(string $hostname): ?string
    {
        $hostname = trim($hostname);
        if ($hostname === '') {
            return null;
        }

        // Already an IP literal (v4 or v6) → nothing to resolve.
        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return $hostname;
        }

        return Cache::remember(
            'dns:a:'.strtolower($hostname),
            self::CACHE_TTL,
            fn (): ?string => $this->query($hostname),
        );
    }

    private function query(string $hostname): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get(self::ENDPOINT, ['name' => $hostname, 'type' => 'A']);

            if (! $response->successful()) {
                return null;
            }

            /** @var array<int, array{type?: int|string, data?: string}> $answers */
            $answers = $response->json('Answer', []);
            if (! is_array($answers)) {
                return null;
            }

            foreach ($answers as $answer) {
                if (! is_array($answer) || (int) ($answer['type'] ?? 0) !== self::DNS_TYPE_A) {
                    continue;
                }

                $data = trim((string) ($answer['data'] ?? ''));
                if (filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    return $data;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('CloudflareDnsResolver: lookup failed', [
                'hostname' => $hostname,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
