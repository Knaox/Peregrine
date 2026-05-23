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
     * @param  list<array{file_id: string, section?: string|null, key: string, max_cap?: float|null, invert?: bool}>  $params
     */
    public function create(Server $server, string $templateId, float $multiplier, Carbon $startAt, Carbon $endAt, array $params, ?int $userId, ?string $recurrence = null, ?Carbon $recurrenceUntil = null): BoostSchedule
    {
        $this->assertNoOverlap($server, $params);

        return BoostSchedule::create([
            'server_id' => $server->id,
            'template_id' => $templateId,
            'multiplier' => $multiplier,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'pending',
            'recurrence' => $recurrence,
            'recurrence_until' => $recurrenceUntil,
            'parameters' => $this->normaliseParams($params),
            'created_by' => $userId,
        ]);
    }

    /**
     * Update a still-pending boost (multiplier / window / recurrence / per-param
     * caps). Active or finished boosts are not editable — they must be cancelled
     * and re-created. Overlap is re-checked, excluding the boost being edited.
     *
     * @param  list<array{file_id: string, section?: string|null, key: string, max_cap?: float|null, invert?: bool}>  $params
     */
    public function update(BoostSchedule $boost, float $multiplier, Carbon $startAt, Carbon $endAt, array $params, ?string $recurrence = null, ?Carbon $recurrenceUntil = null): BoostSchedule
    {
        $server = Server::findOrFail($boost->server_id);
        $this->assertNoOverlap($server, $params, $boost->id);

        $boost->update([
            'multiplier' => $multiplier,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'recurrence' => $recurrence,
            'recurrence_until' => $recurrenceUntil,
            'parameters' => $this->normaliseParams($params),
        ]);

        return $boost;
    }

    /**
     * After a recurring boost completes, create the next occurrence (same window
     * shifted by one interval) unless we've passed `recurrence_until`. Skips any
     * windows already in the past (e.g. scheduler downtime) to the next future
     * one. No overlap check: the just-completed boost has freed its parameters.
     */
    public function rearm(BoostSchedule $boost): ?BoostSchedule
    {
        if ($boost->recurrence === null) {
            return null;
        }

        [$startAt, $endAt] = (new BoostRecurrence)->nextWindow($boost->start_at, $boost->end_at, $boost->recurrence);
        if ($boost->recurrence_until !== null && $startAt->greaterThan($boost->recurrence_until)) {
            return null;
        }

        return BoostSchedule::create([
            'server_id' => $boost->server_id,
            'template_id' => $boost->template_id,
            'multiplier' => $boost->multiplier,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'pending',
            'recurrence' => $boost->recurrence,
            'recurrence_until' => $boost->recurrence_until,
            'parameters' => $this->normaliseParams($boost->parameters),
            'created_by' => $boost->created_by,
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
            // Flip to a transient "cancelling" status SYNCHRONOUSLY first: the
            // list endpoint then reflects the transition on its next poll, and
            // the scheduler (which only matches status='active') can no longer
            // also dispatch a "completed" end — which would otherwise re-arm a
            // recurring boost we're trying to stop. The job then does the safe
            // restore path: stop -> write originals -> start, then archive.
            $boost->update(['status' => 'cancelling']);
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
     * @param  list<array{file_id: string, section?: string|null, key: string, max_cap?: float|null, invert?: bool}>  $params
     */
    private function assertNoOverlap(Server $server, array $params, ?int $excludeId = null): void
    {
        $wanted = [];
        foreach ($params as $param) {
            $wanted[$this->identity($param['file_id'], $param['section'] ?? null, $param['key'])] = true;
        }

        foreach (BoostSchedule::query()->where('server_id', $server->id)->live()->get() as $existing) {
            if ($excludeId !== null && $existing->id === $excludeId) {
                continue;
            }
            foreach ($existing->parameters as $existingParam) {
                if (isset($wanted[$this->identity($existingParam['file_id'], $existingParam['section'] ?? null, $existingParam['key'])])) {
                    throw new BoostOverlapException($existing);
                }
            }
        }
    }

    /**
     * Strip runtime snapshots (original_value / boosted_value) and keep only the
     * stored shape: file_id, section, key, max_cap, invert. `invert` flags a
     * per-parameter "deboost" (divide instead of multiply).
     *
     * @param  list<array<string, mixed>>  $params
     * @return list<array{file_id: string, section: string|null, key: string, max_cap: float|null, invert: bool}>
     */
    private function normaliseParams(array $params): array
    {
        return array_map(static fn (array $param): array => [
            'file_id' => (string) $param['file_id'],
            'section' => $param['section'] ?? null,
            'key' => (string) $param['key'],
            'max_cap' => isset($param['max_cap']) && is_numeric($param['max_cap']) ? (float) $param['max_cap'] : null,
            'invert' => ! empty($param['invert']),
        ], $params);
    }

    private function identity(string $fileId, ?string $section, string $key): string
    {
        return $fileId."\x1f".($section ?? '')."\x1f".$key;
    }
}
