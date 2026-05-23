<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Pelican\CopyScheduleAction;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers CopyScheduleAction — reading a source schedule (cron + tasks) and
 * recreating it on each target server via the Pelican client API.
 *
 * No DB needed: the action only consumes the Server objects it is handed and
 * talks to Pelican (faked). Asserts the per-target aggregation contract: every
 * target is attempted independently and one failure never aborts the others.
 */
class CopyScheduleActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.client_api_key', 'test-client-key');
    }

    private function server(int $id, string $identifier, string $name): Server
    {
        return (new Server)->forceFill(['id' => $id, 'identifier' => $identifier, 'name' => $name]);
    }

    /** Source schedule list payload as Pelican returns it (cron + tasks nested under attributes). */
    private function sourceSchedulePayload(): array
    {
        return ['data' => [[
            'attributes' => [
                'id' => 5,
                'name' => 'Daily restart',
                'cron' => ['minute' => '0', 'hour' => '4', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
                'is_active' => true,
                'only_when_online' => true,
                'relationships' => ['tasks' => ['data' => [
                    ['attributes' => ['id' => 1, 'sequence_id' => 1, 'action' => 'command', 'payload' => 'say hello', 'time_offset' => 0]],
                    ['attributes' => ['id' => 2, 'sequence_id' => 2, 'action' => 'power', 'payload' => 'restart', 'time_offset' => 30]],
                    ['attributes' => ['id' => 3, 'sequence_id' => 7, 'action' => 'backup', 'payload' => 'leftover', 'time_offset' => 60]],
                ]]],
            ],
        ]]];
    }

    public function test_copies_schedule_and_tasks_to_each_target(): void
    {
        Http::fake([
            'pelican.test/api/client/servers/src/schedules' => Http::response($this->sourceSchedulePayload(), 200),
            'pelican.test/api/client/servers/*/schedules' => Http::response(['attributes' => ['id' => 99]], 200),
            'pelican.test/api/client/servers/*/schedules/*/tasks' => Http::response(['attributes' => ['id' => 1]], 200),
        ]);

        $results = app(CopyScheduleAction::class)->execute(
            $this->server(1, 'src', 'Source'),
            5,
            [$this->server(2, 'tgta', 'Target A'), $this->server(3, 'tgtb', 'Target B')],
        );

        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertTrue($r['success']);
            $this->assertSame(99, $r['schedule_id']);
            $this->assertNull($r['error']);
        }

        // 1 list (source) + 2 schedule creates + (3 tasks × 2 targets) = 9.
        Http::assertSentCount(9);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_ends_with($req->url(), '/api/client/servers/tgta/schedules')
            && $req['minute'] === '0' && $req['hour'] === '4' && $req['only_when_online'] === true);
        // Tasks are recreated like a fresh manual task: sequence_id is always 1.
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/servers/tgtb/schedules/99/tasks')
            && $req['action'] === 'power' && $req['payload'] === 'restart' && $req['time_offset'] === 30
            && $req['sequence_id'] === 1);
        // A backup task carries no payload — exactly like the manual flow.
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/schedules/99/tasks')
            && $req['action'] === 'backup' && $req['payload'] === '' && $req['sequence_id'] === 1);
    }

    public function test_one_target_failure_does_not_abort_the_others(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_ends_with($url, '/api/client/servers/src/schedules')) {
                return Http::response($this->sourceSchedulePayload(), 200);
            }
            // Target B's schedule creation fails hard.
            if ($request->method() === 'POST' && str_ends_with($url, '/api/client/servers/tgtb/schedules')) {
                return Http::response(['errors' => [['detail' => 'Node offline']]], 500);
            }

            return Http::response(['attributes' => ['id' => 99]], 200);
        });

        $results = app(CopyScheduleAction::class)->execute(
            $this->server(1, 'src', 'Source'),
            5,
            [$this->server(2, 'tgta', 'Target A'), $this->server(3, 'tgtb', 'Target B')],
        );

        $byId = collect($results)->keyBy('server_id');
        $this->assertTrue($byId[2]['success']);
        $this->assertFalse($byId[3]['success']);
        $this->assertSame('Node offline', $byId[3]['error']);
    }
}
