<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;

class PelicanScheduleService
{
    use MakesClientRequests;

    /**
     * List all schedules for a server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listSchedules(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/schedules")
            ->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Create a new schedule for a server.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createSchedule(string $serverIdentifier, array $data): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/schedules", $data)
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Update an existing schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function updateSchedule(string $serverIdentifier, int $scheduleId, array $data): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/schedules/{$scheduleId}", $data)
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Execute a schedule immediately.
     *
     * @throws RequestException
     */
    public function executeSchedule(string $serverIdentifier, int $scheduleId): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/schedules/{$scheduleId}/execute")
            ->throw();
    }

    /**
     * Delete a schedule from a server.
     *
     * @throws RequestException
     */
    public function deleteSchedule(string $serverIdentifier, int $scheduleId): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/schedules/{$scheduleId}")
            ->throw();
    }

    /**
     * Create a new task for a schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createTask(string $serverIdentifier, int $scheduleId, array $data): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/schedules/{$scheduleId}/tasks", $data)
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Delete a task from a schedule.
     *
     * @throws RequestException
     */
    public function deleteTask(string $serverIdentifier, int $scheduleId, int $taskId): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/schedules/{$scheduleId}/tasks/{$taskId}")
            ->throw();
    }
}
