<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;

/**
 * Thin HTTP client for the Node GameDig sidecar (the plugin ships its source
 * under `sidecar/`). Always returns a structured result: `ok:false` carries the
 * failure reason (sidecar `error`, HTTP status or transport error) so the
 * caller can surface it for debugging — a failed query usually just means the
 * server is offline/unreachable or the address/port don't match.
 *
 * @phpstan-type QueryResult array{ok: bool, online?: int, max?: ?int, name?: ?string, players?: list<string>, error?: string}
 */
class GameQueryClient
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    /**
     * @return QueryResult
     */
    public function query(string $type, string $host, int $port, string $family = ''): array
    {
        $settings = PlayerCounterSettings::make();
        $base = rtrim($settings->sidecarUrl, '/');
        $timeout = $family === 'eos'
            ? (float) config(self::NS.'.eos_timeout', 14)
            : (float) config(self::NS.'.timeout', 5);

        if ($base === '') {
            return ['ok' => false, 'error' => 'sidecar URL not configured'];
        }

        try {
            $request = Http::timeout($timeout)->acceptJson();

            if ($settings->sidecarToken !== '') {
                $request = $request->withToken($settings->sidecarToken);
            }

            $response = $request->post("{$base}/query", [
                'type' => $type,
                'host' => $host,
                'port' => $port,
                'family' => $family,
            ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return $this->fail($type, $host, $port, 'sidecar HTTP '.$response->status());
            }

            if (($data['ok'] ?? false) !== true) {
                return $this->fail($type, $host, $port, is_string($data['error'] ?? null) ? $data['error'] : 'query failed');
            }

            return [
                'ok' => true,
                'online' => max(0, (int) ($data['online'] ?? 0)),
                'max' => isset($data['max']) && $data['max'] !== null ? (int) $data['max'] : null,
                'name' => isset($data['name']) && $data['name'] !== null ? (string) $data['name'] : null,
                'players' => $this->names($data['players'] ?? null),
            ];
        } catch (\Throwable $e) {
            return $this->fail($type, $host, $port, $e->getMessage());
        }
    }

    /**
     * @return array{ok: false, error: string}
     */
    private function fail(string $type, string $host, int $port, string $error): array
    {
        Log::warning('player-counter: query failed', [
            'type' => $type,
            'host' => $host,
            'port' => $port,
            'error' => $error,
        ]);

        return ['ok' => false, 'error' => $error];
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function names($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $names = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $names[] = trim($item);
            }
        }

        return array_values($names);
    }
}
