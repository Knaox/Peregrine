<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pelican\CopyScheduleAction;
use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CopyScheduleRequest;
use App\Http\Requests\Server\CreateScheduleRequest;
use App\Http\Requests\Server\CreateTaskRequest;
use App\Http\Requests\Server\UpdateScheduleRequest;
use App\Models\Server;
use App\Services\Pelican\PelicanScheduleService;
use App\Services\Pelican\ScheduleCache;
use App\Services\Pelican\ScheduleNormalizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class ServerScheduleController extends Controller
{
    public function __construct(
        private PelicanScheduleService $scheduleService,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('readSchedule', $server);

        $data = ScheduleCache::remember($server->identifier, function () use ($server): array {
            $schedules = $this->scheduleService->listSchedules($server->identifier);

            return array_map(ScheduleNormalizer::normalize(...), $schedules);
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
        } catch (RequestException $e) {
            return response()->json(['message' => $e->response->json('errors.0.detail') ?? 'Pelican error'], $e->response->status());
        }

        ScheduleCache::bust($server->identifier);

        $data = $result['attributes'] ?? $result;

        $this->audit($server, 'server.schedule.create', ['name' => $request->validated()['name'] ?? null]);

        return response()->json(['data' => $data], 201);
    }

    public function update(UpdateScheduleRequest $request, Server $server, int $schedule): JsonResponse
    {
        $result = $this->scheduleService->updateSchedule(
            $server->identifier,
            $schedule,
            $request->validated(),
        );

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.update', ['schedule_id' => $schedule]);

        return response()->json(['data' => $result]);
    }

    public function execute(Server $server, int $schedule): JsonResponse
    {
        $this->authorize('updateSchedule', $server);

        $this->scheduleService->executeSchedule($server->identifier, $schedule);

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.execute', ['schedule_id' => $schedule]);

        return response()->json(['message' => 'success']);
    }

    public function destroy(Server $server, int $schedule): JsonResponse
    {
        $this->authorize('deleteSchedule', $server);

        $this->scheduleService->deleteSchedule($server->identifier, $schedule);

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.delete', ['schedule_id' => $schedule]);

        return response()->json(['message' => 'success']);
    }

    public function copy(CopyScheduleRequest $request, Server $server, int $schedule, CopyScheduleAction $action): JsonResponse
    {
        $user = $request->user();

        // Resolve targets, drop the source server, and keep only the ones the
        // user may actually create schedules on. The result list reflects the
        // outcome of each target independently.
        $targets = Server::query()
            ->whereIn('id', $request->validated()['target_server_ids'])
            ->whereNotNull('identifier')
            ->where('id', '!=', $server->id)
            ->get()
            ->filter(fn (Server $target): bool => $user->can('createSchedule', $target))
            ->values();

        if ($targets->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $results = $action->execute($server, $schedule, $targets->all());

        foreach ($results as $result) {
            if ($result['success']) {
                $this->audit($server, 'server.schedule.copy', [
                    'schedule_id' => $schedule,
                    'target_server_id' => $result['server_id'],
                ]);
            }
        }

        return response()->json(['data' => $results]);
    }

    public function storeTask(CreateTaskRequest $request, Server $server, int $schedule): JsonResponse
    {
        $data = $request->validated();
        // Pelican requires sequence_id — auto-set to next available
        if (! isset($data['sequence_id'])) {
            $data['sequence_id'] = 1;
        }

        $result = $this->scheduleService->createTask(
            $server->identifier,
            $schedule,
            $data,
        );

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.task.create', [
            'schedule_id' => $schedule,
            'action' => $data['action'] ?? null,
        ]);

        return response()->json(['data' => $result], 201);
    }

    public function updateTask(CreateTaskRequest $request, Server $server, int $schedule, int $task): JsonResponse
    {
        $data = $request->validated();

        $result = $this->scheduleService->updateTask(
            $server->identifier,
            $schedule,
            $task,
            $data,
        );

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.task.update', [
            'schedule_id' => $schedule,
            'task_id' => $task,
            'action' => $data['action'] ?? null,
        ]);

        return response()->json(['data' => $result]);
    }

    public function destroyTask(Server $server, int $schedule, int $task): JsonResponse
    {
        $this->authorize('updateSchedule', $server);

        $this->scheduleService->deleteTask($server->identifier, $schedule, $task);

        ScheduleCache::bust($server->identifier);

        $this->audit($server, 'server.schedule.task.delete', ['schedule_id' => $schedule, 'task_id' => $task]);

        return response()->json(['message' => 'success']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(Server $server, string $action, array $payload): void
    {
        $request = request();
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: $action,
            server: $server,
            payload: $payload,
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );
    }
}
