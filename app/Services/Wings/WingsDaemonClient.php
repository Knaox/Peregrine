<?php

namespace App\Services\Wings;

use App\Models\Node;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal HTTP client for the Wings daemon API, authenticated with the
 * node's daemon token (`Authorization: Bearer <token>` — the same scheme
 * the Pelican panel itself uses against Wings).
 *
 * Deliberately NO retries and short timeouts: these calls are health
 * probes — we want to observe the real state of the daemon, fast, and a
 * dead node must not hold a page hostage. Wings is not behind the Pelican
 * panel throttle, so probing it directly costs Pelican nothing.
 */
class WingsDaemonClient
{
    private const CONNECT_TIMEOUT_SECONDS = 2;

    private const TIMEOUT_SECONDS = 4;

    /**
     * Lightweight system info: {architecture, cpu_count, kernel_version, os, version}.
     */
    public function getSystem(Node $node): Response
    {
        return $this->request($node)->get('/api/system');
    }

    /**
     * A single server as seen by Wings. Requires the server's FULL uuid
     * (not the short identifier).
     */
    public function getServer(Node $node, string $serverUuid): Response
    {
        return $this->request($node)->get("/api/servers/{$serverUuid}");
    }

    /**
     * Filesystem probe: listing the server root exercises the volume mounts
     * and the Docker filesystem layer — the exact path that turns into
     * generic HTTP 500s when Wings degrades while /api/system still answers
     * (a failure mode observed on LXC/Proxmox installs).
     */
    public function listServerRootFiles(Node $node, string $serverUuid): Response
    {
        return $this->request($node)->get("/api/servers/{$serverUuid}/files/list-directory", [
            'directory' => '/',
        ]);
    }

    private function request(Node $node): PendingRequest
    {
        return Http::withToken((string) $node->daemon_token)
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->baseUrl($node->daemonBaseUrl());
    }
}
