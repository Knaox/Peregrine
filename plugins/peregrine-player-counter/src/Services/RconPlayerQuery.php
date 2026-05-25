<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Player count for RCON-queryable games (ARK), used when the EOS/Epic public
 * query is unavailable (Epic returns 403). Reads the RCON port + admin password
 * from the server's Pelican startup variables (server-side; never logged), runs
 * `ListPlayers`, and parses the connected players (up to 5 names).
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
    public function query(Server $server, array $allocation): array
    {
        if (! $server->identifier) {
            return ['ok' => false, 'error' => 'rcon: no server identifier'];
        }

        $cfg = (array) config(self::NS.'.rcon', []);

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
                (string) ($cfg['command'] ?? 'ListPlayers'),
                (float) ($cfg['timeout'] ?? 4),
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'rcon: '.$e->getMessage()];
        }

        [$online, $players] = $this->parseListPlayers($response);

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
}
