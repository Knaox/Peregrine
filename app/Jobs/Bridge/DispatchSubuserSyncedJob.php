<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Events\Bridge\SubuserSynced;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Parses a Pelican subuser webhook payload and fires the SubuserSynced
 * domain event for the invitations plugin to consume. Core does not
 * persist subusers itself — the plugin owns that table.
 */
class DispatchSubuserSyncedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PelicanEventKind $eventKind,
        public readonly int $pelicanSubuserId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $serverId = (int) ($this->payload['server_id'] ?? 0);
        $userId = (int) ($this->payload['user_id'] ?? 0);

        if ($serverId === 0 || $userId === 0) {
            Log::info('DispatchSubuserSyncedJob: missing server_id or user_id, skipping', [
                'pelican_subuser_id' => $this->pelicanSubuserId,
                'event_kind' => $this->eventKind->value,
            ]);
            return;
        }

        event(new SubuserSynced(
            eventKind: $this->eventKind,
            pelicanSubuserId: $this->pelicanSubuserId,
            pelicanServerId: $serverId,
            pelicanUserId: $userId,
            payload: $this->payload,
        ));

        Log::info('DispatchSubuserSyncedJob: fired SubuserSynced for plugin invitations', [
            'pelican_subuser_id' => $this->pelicanSubuserId,
            'event_kind' => $this->eventKind->value,
        ]);
    }
}
