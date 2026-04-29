<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Pelican\DatabaseHost;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Database change into the local pelican_databases table.
 *
 * Security: the database password is NEVER persisted locally — Pelican
 * stores it `encrypted+hidden` so the webhook payload doesn't carry it,
 * and we don't fetch it on sync. The user-facing UI reads metadata from
 * this table; clicking "Show password" hits a dedicated controller
 * endpoint that calls Pelican Client API live.
 */
class SyncDatabaseFromPelicanWebhookJob implements ShouldQueue
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
        public readonly int $pelicanDatabaseId,
        public readonly int $pelicanServerId,
        public readonly array $payload,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(): void
    {
        if ($this->eventKind === PelicanEventKind::DatabaseDeleted) {
            $this->handleDeletion();
            return;
        }

        // Canary: log critical if Pelican ever ships a password field.
        // Pelican respects $hidden+encrypted casts in toArray() — but a
        // future release might break that contract. We catch it loudly.
        if (isset($this->payload['password'])) {
            Log::critical('SyncDatabaseFromPelicanWebhookJob: payload contains password field — ignoring (canary)', [
                'pelican_database_id' => $this->pelicanDatabaseId,
            ]);
        }

        $server = Server::where('pelican_server_id', $this->pelicanServerId)->first();
        if ($server === null) {
            $this->release(60);
            return;
        }

        $hostId = null;
        if (isset($this->payload['database_host_id'])) {
            $hostId = DatabaseHost::where('pelican_database_host_id', (int) $this->payload['database_host_id'])
                ->value('id');
        }

        $whitelisted = [
            'server_id' => $server->id,
            'pelican_database_host_id' => $hostId,
            'database' => (string) ($this->payload['database'] ?? ''),
            'username' => (string) ($this->payload['username'] ?? ''),
            'remote' => (string) ($this->payload['remote'] ?? '%'),
            'max_connections' => (int) ($this->payload['max_connections'] ?? 0),
            'pelican_created_at' => $this->parseDate($this->payload['created_at'] ?? null),
            'pelican_updated_at' => $this->parseDate($this->payload['updated_at'] ?? null),
        ];

        $database = PelicanDatabase::updateOrCreate(
            ['pelican_database_id' => $this->pelicanDatabaseId],
            $whitelisted,
        );

        Log::info('SyncDatabaseFromPelicanWebhookJob: database mirrored', [
            'pelican_database_id' => $this->pelicanDatabaseId,
            'local_database_id' => $database->id,
            'server_id' => $server->id,
        ]);
    }

    private function handleDeletion(): void
    {
        PelicanDatabase::where('pelican_database_id', $this->pelicanDatabaseId)->delete();
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
