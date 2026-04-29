<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Pelican\Backup;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Backup change into the local pelican_backups table.
 *
 * Pelican backup lifecycle :
 *   - created : backup demanded (completed_at=null, is_successful=false, bytes=0).
 *   - updated : Wings POSTed completion → completed_at + is_successful + bytes set.
 *   - deleted : SoftDelete in Pelican (deleted_at populated).
 *
 * The PruneOrphanedBackupsCommand on Pelican does a `DB::update(...)` mass
 * mutation that bypasses Eloquent events → no webhook fires. The hourly
 * ReconcilePelicanMirrorsJob safety-net rebuilds from listBackups for that
 * case.
 *
 * Security: whitelist explicit field set. Future Pelican releases that add
 * new sensitive fields would be ignored unless added here on purpose.
 */
class SyncBackupFromPelicanWebhookJob implements ShouldQueue
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
        public readonly int $pelicanBackupId,
        public readonly int $pelicanServerId,
        public readonly array $payload,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(): void
    {
        if ($this->eventKind === PelicanEventKind::BackupDeleted) {
            $this->handleDeletion();
            return;
        }

        $server = Server::where('pelican_server_id', $this->pelicanServerId)->first();
        if ($server === null) {
            Log::info('SyncBackupFromPelicanWebhookJob: server not yet mirrored, releasing', [
                'pelican_backup_id' => $this->pelicanBackupId,
                'pelican_server_id' => $this->pelicanServerId,
            ]);
            $this->release(60);
            return;
        }

        $whitelisted = [
            'server_id' => $server->id,
            'uuid' => (string) ($this->payload['uuid'] ?? ''),
            'name' => (string) ($this->payload['name'] ?? 'backup-'.$this->pelicanBackupId),
            'disk' => $this->payload['disk'] ?? null,
            'is_successful' => (bool) ($this->payload['is_successful'] ?? false),
            'is_locked' => (bool) ($this->payload['is_locked'] ?? false),
            'checksum' => $this->payload['checksum'] ?? null,
            'bytes' => (int) ($this->payload['bytes'] ?? 0),
            'completed_at' => $this->parseDate($this->payload['completed_at'] ?? null),
            'pelican_created_at' => $this->parseDate($this->payload['created_at'] ?? null),
            'pelican_updated_at' => $this->parseDate($this->payload['updated_at'] ?? null),
        ];

        $backup = Backup::updateOrCreate(
            ['pelican_backup_id' => $this->pelicanBackupId],
            $whitelisted,
        );

        Log::info('SyncBackupFromPelicanWebhookJob: backup mirrored', [
            'pelican_backup_id' => $this->pelicanBackupId,
            'local_backup_id' => $backup->id,
            'server_id' => $server->id,
            'is_successful' => $backup->is_successful,
        ]);
    }

    private function handleDeletion(): void
    {
        $backup = Backup::where('pelican_backup_id', $this->pelicanBackupId)->first();
        if ($backup === null) {
            return;
        }
        $backup->delete();
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
