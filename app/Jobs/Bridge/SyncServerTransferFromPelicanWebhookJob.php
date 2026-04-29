<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Pelican\ServerTransfer;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors Pelican's server_transfers table locally so the UI can surface
 * a "transfer in progress" badge per server without polling Pelican.
 */
class SyncServerTransferFromPelicanWebhookJob implements ShouldQueue
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
        public readonly int $pelicanServerTransferId,
        public readonly array $payload,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(): void
    {
        if ($this->eventKind === PelicanEventKind::ServerTransferDeleted) {
            ServerTransfer::where('pelican_server_transfer_id', $this->pelicanServerTransferId)->delete();
            return;
        }

        $pelicanServerId = (int) ($this->payload['server_id'] ?? 0);
        $server = Server::where('pelican_server_id', $pelicanServerId)->first();
        if ($server === null) {
            $this->release(60);
            return;
        }

        ServerTransfer::updateOrCreate(
            ['pelican_server_transfer_id' => $this->pelicanServerTransferId],
            [
                'server_id' => $server->id,
                'successful' => isset($this->payload['successful'])
                    ? (bool) $this->payload['successful']
                    : null,
                'old_node' => $this->payload['old_node'] ?? null,
                'new_node' => $this->payload['new_node'] ?? null,
                'old_allocation' => $this->payload['old_allocation'] ?? null,
                'new_allocation' => $this->payload['new_allocation'] ?? null,
                'old_additional_allocations' => $this->payload['old_additional_allocations'] ?? null,
                'new_additional_allocations' => $this->payload['new_additional_allocations'] ?? null,
                'archived' => (bool) ($this->payload['archived'] ?? false),
                'pelican_created_at' => $this->parseDate($this->payload['created_at'] ?? null),
                'pelican_updated_at' => $this->parseDate($this->payload['updated_at'] ?? null),
            ],
        );

        Log::info('SyncServerTransferFromPelicanWebhookJob: transfer mirrored', [
            'pelican_server_transfer_id' => $this->pelicanServerTransferId,
            'server_id' => $server->id,
            'successful' => $this->payload['successful'] ?? null,
        ]);
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
