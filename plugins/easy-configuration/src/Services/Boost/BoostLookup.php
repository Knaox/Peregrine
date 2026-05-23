<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

use Plugins\EasyConfiguration\Models\BoostSchedule;

/**
 * Builds a per-parameter map of the live (pending/active) boosts on a server,
 * keyed by "fileId\x1fsection\x1fkey". Shared by the config reader (to show the
 * baseline + boost badge) and the writer (Option 2 baseline editing).
 *
 * @phpstan-type BoostInfo array{id: int, status: string, multiplier: float, start_at: mixed, end_at: mixed, original_value: ?string, boosted_value: ?string, max_cap: ?float, invert: bool}
 */
final class BoostLookup
{
    /** @return array<string, array<string, mixed>> */
    public function forServer(int $serverId): array
    {
        $map = [];

        foreach (BoostSchedule::query()->where('server_id', $serverId)->live()->get() as $boost) {
            foreach ($boost->parameters as $param) {
                $identity = self::identity((string) $param['file_id'], $param['section'] ?? null, (string) $param['key']);
                $map[$identity] = [
                    'id' => $boost->id,
                    'status' => $boost->status,
                    'multiplier' => (float) $boost->multiplier,
                    'start_at' => $boost->start_at,
                    'end_at' => $boost->end_at,
                    'original_value' => $param['original_value'] ?? null,
                    'boosted_value' => $param['boosted_value'] ?? null,
                    'max_cap' => isset($param['max_cap']) && is_numeric($param['max_cap']) ? (float) $param['max_cap'] : null,
                    'invert' => ! empty($param['invert']),
                ];
            }
        }

        return $map;
    }

    public static function identity(string $fileId, ?string $section, string $key): string
    {
        return $fileId."\x1f".($section ?? '')."\x1f".$key;
    }
}
