<?php

namespace App\Actions\Pelican;

use App\Models\Server;
use App\Services\Pelican\PelicanScheduleService;
use App\Services\Pelican\ScheduleCache;
use App\Services\Pelican\ScheduleNormalizer;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Copies one schedule (cron + all of its tasks) from a source server onto one
 * or more target servers via the Pelican client API.
 *
 * Each target is handled independently: a failure on one target never aborts
 * the others, and the per-target outcome is returned so the caller can report
 * success/failure server by server.
 *
 * Authorization is the caller's responsibility — only servers the user may
 * already create schedules on (schedule.create) should be passed as targets.
 */
final class CopyScheduleAction
{
    public function __construct(
        private readonly PelicanScheduleService $scheduleService,
    ) {}

    /**
     * @param  array<int, Server>  $targets
     * @return array<int, array{server_id: int, server_name: string, success: bool, error: ?string, schedule_id: ?int}>
     *
     * @throws RequestException when the source schedule cannot be read
     */
    public function execute(Server $source, int $scheduleId, array $targets): array
    {
        $schedule = $this->findSourceSchedule($source, $scheduleId);

        return array_map(
            fn (Server $target): array => $this->copyToTarget($target, $schedule),
            array_values($targets),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    private function findSourceSchedule(Server $source, int $scheduleId): array
    {
        foreach ($this->scheduleService->listSchedules($source->identifier) as $raw) {
            $normalized = ScheduleNormalizer::normalize($raw);
            if ((int) ($normalized['id'] ?? 0) === $scheduleId) {
                return $normalized;
            }
        }

        throw new \RuntimeException("Schedule #{$scheduleId} not found on server {$source->identifier}.");
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array{server_id: int, server_name: string, success: bool, error: ?string, schedule_id: ?int}
     */
    private function copyToTarget(Server $target, array $schedule): array
    {
        $result = [
            'server_id' => $target->id,
            'server_name' => (string) $target->name,
            'success' => false,
            'error' => null,
            'schedule_id' => null,
        ];

        try {
            $created = $this->scheduleService->createSchedule($target->identifier, [
                'name' => $schedule['name'] ?? 'Copied schedule',
                'minute' => $schedule['minute'] ?? '*',
                'hour' => $schedule['hour'] ?? '*',
                'day_of_month' => $schedule['day_of_month'] ?? '*',
                'month' => $schedule['month'] ?? '*',
                'day_of_week' => $schedule['day_of_week'] ?? '*',
                'is_active' => (bool) ($schedule['is_active'] ?? true),
                'only_when_online' => (bool) ($schedule['only_when_online'] ?? false),
            ]);

            $newScheduleId = (int) ($created['id'] ?? 0);

            foreach (array_values($schedule['tasks'] ?? []) as $task) {
                $action = $task['action'] ?? 'command';
                // Recreate each task exactly as the manual "create task" flow does
                // (AddTaskForm + ServerScheduleController::storeTask): a backup
                // task carries no payload, and a freshly created task is always
                // sent with sequence_id = 1 — never the source's own sequence_id.
                $this->scheduleService->createTask($target->identifier, $newScheduleId, [
                    'action' => $action,
                    'payload' => $action === 'backup' ? '' : (string) ($task['payload'] ?? ''),
                    'time_offset' => (int) ($task['time_offset'] ?? 0),
                    'sequence_id' => 1,
                ]);
            }

            ScheduleCache::bust($target->identifier);

            $result['success'] = true;
            $result['schedule_id'] = $newScheduleId;
        } catch (RequestException $e) {
            $result['error'] = $e->response->json('errors.0.detail') ?? 'Pelican error';
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
