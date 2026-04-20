<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CreateScheduleRequest;
use App\Http\Requests\Server\CreateTaskRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ServerScheduleController extends Controller
{
    public function __construct(
        private PelicanScheduleService $scheduleService,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('readSchedule', $server);

        $data = Cache::remember("server_schedules:{$server->identifier}", 300, function () use ($server): array {
            $schedules = $this->scheduleService->listSchedules($server->identifier);

            return array_map(function (array $schedule): array {
                $attrs = $schedule['attributes'] ?? $schedule;
                // Flatten cron object into top-level fields
                if (isset($attrs['cron'])) {
                    $attrs['minute'] = $attrs['cron']['minute'] ?? '*';
                    $attrs['hour'] = $attrs['cron']['hour'] ?? '*';
                    $attrs['day_of_month'] = $attrs['cron']['day_of_month'] ?? '*';
                    $attrs['month'] = $attrs['cron']['month'] ?? '*';
                    $attrs['day_of_week'] = $attrs['cron']['day_of_week'] ?? '*';
                    unset($attrs['cron']);
                }
                // Flatten tasks from relationships
                $rawTasks = $attrs['relationships']['tasks']['data'] ?? [];
                $attrs['tasks'] = array_map(
                    fn (array $task) => $task['attributes'] ?? $task,
                    $rawTasks,
                );
                unset($attrs['relationships']);
                return $attrs;
            }, $schedules);
        });

        return response()->json(['data' => $data]);
    }

    public function store(CreateScheduleRequest $request, Server $server): JsonResponse
    {
        try {
            $result = $this->scheduleService->createSchedule(
                $server->identifier,
                $request->validated(),
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return response()->json(['message' => $e->response->json('errors.0.detail') ?? 'Pelican error'], $e->response->status());
        }

        Cache::forget("server_schedules:{$server->identifier}");

        $data = $result['attributes'] ?? $result;

        return response()->json(['data' => $data], 201);
    }

    public function update(CreateScheduleRequest $request, Server $server, int $schedule): JsonResponse
    {
        $this->authorize('updateSchedule', $server);

        $result = $this->scheduleService->updateSchedule(
            $server->identifier,
            $schedule,
            $request->validated(),
        );

        Cache::forget("server_schedules:{$server->identifier}");

        return response()->json(['data' => $result]);
    }

    public function execute(Server $server, int $schedule): JsonResponse
    {
        $this->authorize('updateSchedule', $server);

        $this->scheduleService->executeSchedule($server->identifier, $schedule);

        Cache::forget("server_schedules:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }

    public function destroy(Server $server, int $schedule): JsonResponse
    {
        $this->authorize('deleteSchedule', $server);

        $this->scheduleService->deleteSchedule($server->identifier, $schedule);

        Cache::forget("server_schedules:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }

    public function storeTask(CreateTaskRequest $request, Server $server, int $schedule): JsonResponse
    {
        $data = $request->validated();
        // Pelican requires sequence_id — auto-set to next available
        if (!isset($data['sequence_id'])) {
            $data['sequence_id'] = 1;
        }

        $result = $this->scheduleService->createTask(
            $server->identifier,
            $schedule,
            $data,
        );

        Cache::forget("server_schedules:{$server->identifier}");

        return response()->json(['data' => $result], 201);
    }

    public function destroyTask(Server $server, int $schedule, int $task): JsonResponse
    {
        $this->authorize('updateSchedule', $server);

        $this->scheduleService->deleteTask($server->identifier, $schedule, $task);

        Cache::forget("server_schedules:{$server->identifier}");

        return response()->json(['message' => 'success']);
    }
}
