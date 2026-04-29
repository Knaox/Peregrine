<?php

namespace App\Services\Mirror;

use App\Models\MirrorBackfillProgress;
use Illuminate\Support\Facades\Log;

/**
 * Pilote la séquence de backfill déclenchée par le bouton "Activer la
 * lecture DB locale" de la page /admin/pelican-webhook-settings.
 *
 * Ordre des étapes (chacune dépend de la précédente) :
 *   1. users   — sans `users.pelican_user_id`, le backfill subusers ne
 *                peut pas matcher l'email aux comptes locaux.
 *   2. allocations — filtre serverId=null, n'écrit que les ports attribués.
 *   3. databases   — itère par serveur local.
 *   4. backups     — itère par serveur local.
 *   5. subusers    — itère par serveur local + lookup user par email.
 *
 * Chaque backfiller capture ses propres exceptions et retourne un report
 * structuré ; l'orchestrateur agrège, log, et persiste le tout dans
 * `mirror_backfill_progress.report`. Aucune étape n'est fatale : si
 * allocations plante, databases tourne quand même.
 *
 * `EnableLocalDbReadJob` flippe ensuite `mirror_reads_enabled=true`
 * UNIQUEMENT si le report ne contient zéro erreur ; l'admin garde la
 * main pour reflusher manuellement en cas d'échec partiel.
 */
class MirrorBackfillOrchestrator
{
    public function __construct(
        private readonly UserMirrorBackfiller $users,
        private readonly AllocationMirrorBackfiller $allocations,
        private readonly DatabaseMirrorBackfiller $databases,
        private readonly BackupMirrorBackfiller $backups,
        private readonly SubuserMirrorBackfiller $subusers,
    ) {}

    /**
     * @return array<string, array<string, int>>
     */
    public function run(?MirrorBackfillProgress $progress = null): array
    {
        $report = [];
        $startedAt = microtime(true);

        $steps = [
            'users' => fn () => $this->users->run(),
            'allocations' => fn () => $this->allocations->run(),
            'databases' => fn () => $this->databases->run(),
            'backups' => fn () => $this->backups->run(),
            'subusers' => fn () => $this->subusers->run(),
        ];

        foreach ($steps as $name => $step) {
            $stepStart = microtime(true);
            Log::info("MirrorBackfill: starting {$name}");

            try {
                $stepReport = $step();
            } catch (\Throwable $e) {
                $stepReport = ['errors' => 1, 'fatal' => 1];
                Log::error("MirrorBackfill: {$name} threw uncaught exception", [
                    'error' => $e->getMessage(),
                ]);
            }

            $stepReport['duration_ms'] = (int) round((microtime(true) - $stepStart) * 1000);
            $report[$name] = $stepReport;

            Log::info("MirrorBackfill: finished {$name}", $stepReport);
        }

        $report['_total'] = [
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'errors' => $this->countErrors($report),
        ];

        return $report;
    }

    public function isAnyRunning(): bool
    {
        return MirrorBackfillProgress::isAnyRunning();
    }

    /**
     * @param array<string, array<string, int>> $report
     */
    private function countErrors(array $report): int
    {
        $total = 0;

        foreach ($report as $key => $stepReport) {
            if ($key === '_total') {
                continue;
            }
            $total += (int) ($stepReport['errors'] ?? 0);
        }

        return $total;
    }
}
