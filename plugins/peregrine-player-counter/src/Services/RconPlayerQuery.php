<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Player count for RCON-queryable games (ARK, Palworld, …), used when the game
 * has no usable wire query (ARK's EOS query returns a 403; Palworld has no A2S).
 * Reads the RCON port + admin password from the server's Pelican startup
 * variables (server-side; never logged), runs the per-type command (ARK
 * `ListPlayers`, Palworld `ShowPlayers`) and parses the connected players (up to
 * 5 names) according to that game's response format.
 *
 * @phpstan-type RconResult array{ok: bool, online?: int, max?: ?int, players?: list<string>, error?: string}
 */
class RconPlayerQuery
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    public function __construct(
        private PelicanClientService $pelican,
        private RconClient $rcon,
    ) {}

    /**
     * @param  array{ip: string, port: int}  $allocation
     * @return RconResult
     */
    public function query(Server $server, array $allocation, string $type = ''): array
    {
        if (! $server->identifier) {
            return ['ok' => false, 'error' => 'rcon: no server identifier'];
        }

        $cfg = (array) config(self::NS.'.rcon', []);
        $perType = (array) ($cfg['commands'][$type] ?? []);
        $command = (string) ($perType['command'] ?? $cfg['command'] ?? 'ListPlayers');
        $format = (string) ($perType['format'] ?? $cfg['format'] ?? 'ark');

        try {
            $vars = $this->pelican->getStartupVariables($server->identifier);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'rcon: could not read startup variables'];
        }

        $map = [];
        foreach ($vars as $v) {
            $key = $v['env_variable'] ?? null;
            if (is_string($key)) {
                $map[$key] = (string) ($v['server_value'] ?? '');
            }
        }

        $password = $this->firstValue($map, (array) ($cfg['password_vars'] ?? []));
        $portValue = $this->firstValue($map, (array) ($cfg['port_vars'] ?? []));
        $port = $portValue !== '' ? (int) $portValue : 0;

        if ($password === '') {
            return ['ok' => false, 'error' => 'rcon: admin password variable not found (adjust rcon.password_vars)'];
        }
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => 'rcon: RCON port variable not found (adjust rcon.port_vars)'];
        }

        $maxValue = $this->firstValue($map, (array) ($cfg['max_players_vars'] ?? []));
        $max = $maxValue !== '' ? (int) $maxValue : null;

        try {
            $response = $this->rcon->command(
                $allocation['ip'],
                $port,
                $password,
                $command,
                (float) ($cfg['timeout'] ?? 4),
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'rcon: '.$e->getMessage()];
        }

        [$online, $players] = $this->parse($format, $response);

        return ['ok' => true, 'online' => $online, 'max' => $max, 'players' => $players];
    }

    /**
     * @param  array<string, string>  $map
     * @param  list<string>  $candidates
     */
    private function firstValue(array $map, array $candidates): string
    {
        foreach ($candidates as $name) {
            if (isset($map[$name]) && trim($map[$name]) !== '') {
                return trim($map[$name]);
            }
        }

        return '';
    }

    /**
     * Route the RCON response to the matching parser. Returns [count, names].
     *
     * @return array{0: int, 1: list<string>}
     */
    private function parse(string $format, string $response): array
    {
        return match ($format) {
            'palworld' => $this->parseShowPlayers($response),
            default => $this->parseListPlayers($response),
        };
    }

    /**
     * Parse ARK's `ListPlayers`: "No Players Connected", or numbered lines like
     * "0. PlayerName, 0002a8…". Returns [count, up-to-5 names].
     *
     * @return array{0: int, 1: list<string>}
     */
    private function parseListPlayers(string $response): array
    {
        if (stripos($response, 'no players') !== false) {
            return [0, []];
        }

        $names = [];
        foreach (preg_split('/\r\n|\r|\n/', $response) ?: [] as $line) {
            if (preg_match('/^\s*\d+\.\s*(.+?)\s*,/', $line, $m) === 1) {
                $names[] = trim($m[1]);
            }
        }

        return [count($names), array_slice($names, 0, 5)];
    }

    /**
     * Parse Palworld's `ShowPlayers`: a CSV with a "name,playeruid,steamid"
     * header line, then one row per connected player. The display name is
     * everything before the first comma. Returns [count, up-to-5 names].
     *
     * @return array{0: int, 1: list<string>}
     */
    private function parseShowPlayers(string $response): array
    {
        $names = [];
        foreach (preg_split('/\r\n|\r|\n/', $response) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Skip the CSV header row.
            if (stripos($line, 'name,') === 0 && stripos($line, 'playeruid') !== false) {
                continue;
            }
            $name = trim(explode(',', $line)[0]);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return [count($names), array_slice($names, 0, 5)];
    }
}
