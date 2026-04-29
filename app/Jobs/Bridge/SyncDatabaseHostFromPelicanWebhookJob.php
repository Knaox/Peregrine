<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Pelican\DatabaseHost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican DatabaseHost change locally. Password is NEVER stored
 * (Pelican $hidden + encrypted cast keeps it out of the webhook payload).
 */
class SyncDatabaseHostFromPelicanWebhookJob implements ShouldQueue
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
        public readonly int $pelicanDatabaseHostId,
        public readonly array $payload,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(): void
    {
        if ($this->eventKind === PelicanEventKind::DatabaseHostDeleted) {
            DatabaseHost::where('pelican_database_host_id', $this->pelicanDatabaseHostId)->delete();
            return;
        }

        if (isset($this->payload['password'])) {
            Log::critical('SyncDatabaseHostFromPelicanWebhookJob: payload contains password field — ignoring (canary)', [
                'pelican_database_host_id' => $this->pelicanDatabaseHostId,
            ]);
        }

        DatabaseHost::updateOrCreate(
            ['pelican_database_host_id' => $this->pelicanDatabaseHostId],
            [
                'name' => (string) ($this->payload['name'] ?? 'host-'.$this->pelicanDatabaseHostId),
                'host' => (string) ($this->payload['host'] ?? ''),
                'port' => (int) ($this->payload['port'] ?? 3306),
                'username' => (string) ($this->payload['username'] ?? ''),
                'max_databases' => (int) ($this->payload['max_databases'] ?? 0),
                'pelican_created_at' => $this->parseDate($this->payload['created_at'] ?? null),
                'pelican_updated_at' => $this->parseDate($this->payload['updated_at'] ?? null),
            ],
        );
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
