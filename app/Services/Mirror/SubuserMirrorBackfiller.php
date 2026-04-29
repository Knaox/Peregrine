<?php

namespace App\Services\Mirror;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Plugins\Invitations\Models\PelicanSubuser;
use Plugins\Invitations\Services\PelicanSubuserService;

/**
 * Backfills `invitations_pelican_subusers` from the Pelican Client API.
 *
 * Why this exists : the invitations plugin populates its mirror only via
 * the `Server\SubUserAdded/Removed` webhooks — there is no rétroactive
 * source. Without a backfill the table stays empty on existing installs
 * the moment "Activer la lecture DB locale" gets flipped on, so the
 * server-subuser page silently shows nothing until a subuser change
 * fires the next webhook.
 *
 * The Client API `/api/client/servers/{id}/users` returns
 * (uuid, username, email, permissions) — NOT the numeric subuser_id /
 * user_id. We derive `pelican_user_id` by looking up the local user by
 * email (whose `users.pelican_user_id` is filled by UserMirrorBackfiller
 * earlier in the orchestration). If no local user matches, we skip and
 * log — the subuser will be picked up later when its owner gets
 * imported by a subsequent backfill or webhook.
 */
final class SubuserMirrorBackfiller
{
    public function __construct(
        private readonly PelicanSubuserService $subusers,
    ) {}

    /**
     * @return array{processed:int,written:int,skipped_no_local_user:int,removed_orphans:int,errors:int}
     */
    public function run(): array
    {
        $report = [
            'processed' => 0,
            'written' => 0,
            'skipped_no_local_user' => 0,
            'removed_orphans' => 0,
            'errors' => 0,
        ];

        $seenServerUserPairs = [];

        foreach (Server::query()->whereNotNull('identifier')->whereNotNull('pelican_server_id')->cursor() as $server) {
            try {
                $remote = $this->subusers->listSubusersFromApi($server->identifier);
            } catch (\Throwable $e) {
                $report['errors']++;
                Log::warning('SubuserMirrorBackfiller: server fetch failed', [
                    'server_id' => $server->id,
                    'identifier' => $server->identifier,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($remote as $sub) {
                $report['processed']++;

                $email = isset($sub['email']) ? strtolower((string) $sub['email']) : null;
                if ($email === null || $email === '') {
                    $report['skipped_no_local_user']++;
                    continue;
                }

                $pelicanUserId = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->whereNotNull('pelican_user_id')
                    ->value('pelican_user_id');

                if ($pelicanUserId === null) {
                    $report['skipped_no_local_user']++;
                    continue;
                }

                $permissions = is_array($sub['permissions'] ?? null) ? $sub['permissions'] : [];

                PelicanSubuser::query()->updateOrCreate(
                    [
                        'pelican_server_id' => (int) $server->pelican_server_id,
                        'pelican_user_id' => (int) $pelicanUserId,
                    ],
                    [
                        // pelican_subuser_id stays null — the next webhook
                        // arrival on this subuser will fill it in.
                        'permissions' => $permissions,
                        'pelican_created_at' => $this->parseDate($sub['created_at'] ?? null),
                    ],
                );

                $seenServerUserPairs[] = $server->pelican_server_id.':'.$pelicanUserId;
                $report['written']++;
            }
        }

        $report['removed_orphans'] = $this->pruneOrphans($seenServerUserPairs);

        return $report;
    }

    /**
     * Drop mirror rows whose (server, user) pair wasn't seen during this
     * backfill — the subuser was removed on Pelican between two runs and
     * we missed the webhook.
     *
     * @param  list<string>  $seenPairs  formatted "<server_id>:<user_id>"
     */
    private function pruneOrphans(array $seenPairs): int
    {
        $removed = 0;

        PelicanSubuser::query()->cursor()->each(function (PelicanSubuser $row) use ($seenPairs, &$removed): void {
            $key = $row->pelican_server_id.':'.$row->pelican_user_id;
            if (! in_array($key, $seenPairs, true)) {
                $row->delete();
                $removed++;
            }
        });

        return $removed;
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
