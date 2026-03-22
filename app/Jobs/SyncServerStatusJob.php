<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncServerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PelicanClientService $clientService): void
    {
        $servers = Server::whereNotNull('identifier')->get();

        foreach ($servers as $server) {
            try {
                $resources = $clientService->getServerResources($server->identifier);

                $newStatus = match ($resources->state) {
                    'running', 'starting' => 'running',
                    'stopping', 'stopped' => 'stopped',
                    'offline' => 'offline',
                    default => $server->status,
                };

                if ($server->status !== $newStatus && ! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $server->update(['status' => $newStatus]);
                }
            } catch (\Throwable) {
                // If API call fails, mark as offline (unless suspended/terminated)
                if (! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $server->update(['status' => 'offline']);
                }
            }
        }
    }
}
