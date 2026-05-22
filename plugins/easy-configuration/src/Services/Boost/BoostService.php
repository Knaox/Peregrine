<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

use App\Models\Server;
use Illuminate\Support\Carbon;
use Plugins\EasyConfiguration\Exceptions\BoostOverlapException;
use Plugins\EasyConfiguration\Jobs\EndBoostJob;
use Plugins\EasyConfiguration\Models\BoostHistory;
use Plugins\EasyConfiguration\Models\BoostSchedule;

/**
 * Creates, cancels and archives boosts. Enforces the "one boost per parameter"
 * rule: a new boost may not touch a (file, section, key) already covered by a
 * pending or active boost on the same server.
 */
final class BoostService
{
    /**
     * @param  list<array{file_id: string, section?: string|null, key: string, max_cap?: float|null}>  $params
     */
    public function create(Server $server, string $templateId, float $multiplier, Carbon $startAt, Carbon $endAt, array $params, ?int $userId): BoostSchedule
    {
        $this->assertNoOverlap($server, $params);

        return BoostSchedule::create([
            'server_id' => $server->id,
            'template_id' => $templateId,
            'multiplier' => $multiplier,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'pending',
            'parameters' => array_map(static fn (array $param): array => [
                'file_id' => $param['file_id'],
                'section' => $param['section'] ?? null,
                'key' => $param['key'],
                'max_cap' => $param['max_cap'] ?? null,
            ], $params),
            'created_by' => $userId,
        ]);
    }

    public function cancel(BoostSchedule $boost): void
    {
        if ($boost->status === 'pending') {
            $this->archive($boost, 'cancelled');
            $boost->delete();

            return;
        }

        if ($boost->status === 'active') {
            // Safe restore path: stop -> write originals -> start, then archive.
            EndBoostJob::dispatch($boost->id, 'cancelled');
        }
    }

    public function archive(BoostSchedule $boost, string $finalStatus, ?string $note = null): void
    {
        BoostHistory::create([
            'server_id' => $boost->server_id,
            'template_id' => $boost->template_id,
            'multiplier' => $boost->multiplier,
            'start_at' => $boost->start_at,
            'end_at' => $boost->end_at,
            'final_status' => $finalStatus,
            'parameters' => $boost->parameters,
            'applied_at' => $boost->applied_at,
            'ended_at' => $finalStatus === 'completed' || $finalStatus === 'cancelled' ? now() : $boost->ended_at,
            'created_by' => $boost->created_by,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<array{file_id: string, section?: string|null, key: string, max_cap?: float|null}>  $params
     */
    private function assertNoOverlap(Server $server, array $params): void
    {
        $wanted = [];
        foreach ($params as $param) {
            $wanted[$this->identity($param['file_id'], $param['section'] ?? null, $param['key'])] = true;
        }

        foreach (BoostSchedule::query()->where('server_id', $server->id)->live()->get() as $existing) {
            foreach ($existing->parameters as $existingParam) {
                if (isset($wanted[$this->identity($existingParam['file_id'], $existingParam['section'] ?? null, $existingParam['key'])])) {
                    throw new BoostOverlapException($existing);
                }
            }
        }
    }

    private function identity(string $fileId, ?string $section, string $key): string
    {
        return $fileId."\x1f".($section ?? '')."\x1f".$key;
    }
}
