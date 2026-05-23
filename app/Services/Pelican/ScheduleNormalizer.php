<?php

namespace App\Services\Pelican;

/**
 * Flattens a raw Pelican schedule payload into the flat shape the SPA and the
 * copy flow consume: cron fields hoisted to the top level and tasks pulled out
 * of the JSON:API relationships envelope. Shared by ServerScheduleController
 * (list endpoint) and CopyScheduleAction so the two never drift.
 */
class ScheduleNormalizer
{
    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public static function normalize(array $schedule): array
    {
        $attrs = $schedule['attributes'] ?? $schedule;

        // Flatten cron object into top-level fields.
        if (isset($attrs['cron'])) {
            $attrs['minute'] = $attrs['cron']['minute'] ?? '*';
            $attrs['hour'] = $attrs['cron']['hour'] ?? '*';
            $attrs['day_of_month'] = $attrs['cron']['day_of_month'] ?? '*';
            $attrs['month'] = $attrs['cron']['month'] ?? '*';
            $attrs['day_of_week'] = $attrs['cron']['day_of_week'] ?? '*';
            unset($attrs['cron']);
        }

        // Flatten tasks from the relationships envelope.
        $rawTasks = $attrs['relationships']['tasks']['data'] ?? [];
        $attrs['tasks'] = array_map(
            fn (array $task) => $task['attributes'] ?? $task,
            $rawTasks,
        );
        unset($attrs['relationships']);

        return $attrs;
    }
}
