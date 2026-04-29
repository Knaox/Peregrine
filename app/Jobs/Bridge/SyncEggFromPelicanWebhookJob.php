<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\Egg;
use App\Models\Nest;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Egg or EggVariable change into the local DB.
 *
 * Triggered by Pelican webhooks: `created/updated/deleted` on Egg or
 * EggVariable. Replaces the manual `sync:eggs` command for the common
 * case of an admin importing or editing an egg in Pelican.
 *
 * Behaviour:
 *
 *   EggCreated / EggUpdated / EggVariableCreated / EggVariableUpdated /
 *   EggVariableDeleted
 *     - Always refetch the parent EGG via Pelican Application API
 *       (`getEgg`). The full egg payload includes its variables, so a
 *       single API call covers all variable mutations cleanly.
 *     - Upsert the egg + the dérivé Nest by pelican_egg_id /
 *       pelican_nest_id.
 *
 *   EggDeleted
 *     - Refuse if any local server or server plan still uses this egg
 *       (would orphan FK references). Admin must reassign first.
 *     - Otherwise hard-delete the local egg row.
 *
 * The job constructor takes both the eggId AND the eventKind so we can
 * tell EggDeleted apart from EggVariableDeleted (the latter still needs
 * the egg refetched, not deleted).
 */
class SyncEggFromPelicanWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public readonly int $pelicanEggId,
        public readonly PelicanEventKind $eventKind,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        if ($this->eventKind === PelicanEventKind::EggDeleted) {
            $this->handleEggDeletion();
            return;
        }

        try {
            $pelicanEgg = $pelican->getEgg($this->pelicanEggId);
        } catch (RequestException $e) {
            if ($e->response?->status() === 404) {
                Log::info('SyncEggFromPelicanWebhookJob: pelican egg not found, skipping', [
                    'pelican_egg_id' => $this->pelicanEggId,
                    'event_kind' => $this->eventKind->value,
                ]);
                return;
            }
            throw $e;
        }

        $nestId = null;
        if ($pelicanEgg->nestId > 0) {
            $nest = Nest::updateOrCreate(
                ['pelican_nest_id' => $pelicanEgg->nestId],
                ['name' => 'Nest #'.$pelicanEgg->nestId],
            );
            $nestId = $nest->id;
        }

        $egg = Egg::updateOrCreate(
            ['pelican_egg_id' => $pelicanEgg->id],
            [
                'nest_id' => $nestId,
                'name' => $pelicanEgg->name,
                'docker_image' => $pelicanEgg->dockerImage,
                'startup' => $pelicanEgg->startup,
                'description' => $pelicanEgg->description,
                'tags' => $pelicanEgg->tags,
                'features' => $pelicanEgg->features,
            ],
        );

        Log::info('SyncEggFromPelicanWebhookJob: egg mirrored', [
            'pelican_egg_id' => $this->pelicanEggId,
            'local_egg_id' => $egg->id,
            'event_kind' => $this->eventKind->value,
            'was_recently_created' => $egg->wasRecentlyCreated,
        ]);
    }

    private function handleEggDeletion(): void
    {
        $egg = Egg::where('pelican_egg_id', $this->pelicanEggId)->first();
        if ($egg === null) {
            return;
        }

        $serverCount = Server::where('egg_id', $egg->id)->count();
        $planCount = ServerPlan::where('egg_id', $egg->id)->count();

        if ($serverCount > 0 || $planCount > 0) {
            Log::warning('SyncEggFromPelicanWebhookJob: refusing to delete egg with local references', [
                'pelican_egg_id' => $this->pelicanEggId,
                'local_egg_id' => $egg->id,
                'attached_servers' => $serverCount,
                'attached_plans' => $planCount,
            ]);
            return;
        }

        $egg->delete();

        Log::info('SyncEggFromPelicanWebhookJob: egg removed', [
            'pelican_egg_id' => $this->pelicanEggId,
            'local_egg_id' => $egg->id,
        ]);
    }
}
