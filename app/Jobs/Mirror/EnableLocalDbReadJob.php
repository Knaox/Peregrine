<?php

namespace App\Jobs\Mirror;

use App\Models\MirrorBackfillProgress;
use App\Services\Mirror\MirrorBackfillOrchestrator;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Lance la séquence "Activer la lecture DB locale" en arrière-plan.
 *
 * - tries=1 : pas de retry auto. En cas d'échec, l'admin clique à nouveau
 *   sur le bouton (UX explicite plutôt que duplication silencieuse).
 * - timeout=900 (15 min) : large marge pour les installs avec beaucoup
 *   de serveurs ; chaque step a déjà des try/catch internes alors un
 *   timeout signale un problème réseau plus large.
 *
 * Workflow :
 *   1. Crée une row `mirror_backfill_progress` state=running
 *   2. Lance l'orchestrateur, agrège le report
 *   3. Si zéro erreur fatale → flippe `mirror_reads_enabled=true` et
 *      marque le progress completed. Sinon → garde le flag à false et
 *      marque failed (mais avec le report complet pour debug).
 *
 * Idempotent vis-à-vis de la flag : ré-exécuter alors que le flag est
 * déjà à true ne casse rien — chaque backfiller est idempotent
 * (updateOrCreate + cleanup orphelins).
 */
final class EnableLocalDbReadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        public readonly int $progressId,
    ) {}

    public function handle(
        MirrorBackfillOrchestrator $orchestrator,
        SettingsService $settings,
    ): void {
        $progress = MirrorBackfillProgress::query()->find($this->progressId);
        if ($progress === null) {
            Log::warning('EnableLocalDbReadJob: progress row missing, aborting', [
                'progress_id' => $this->progressId,
            ]);

            return;
        }

        try {
            $report = $orchestrator->run($progress);
        } catch (\Throwable $e) {
            $progress->markFailed($e);
            Log::error('EnableLocalDbReadJob: orchestrator threw', [
                'progress_id' => $progress->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $errors = (int) ($report['_total']['errors'] ?? 0);

        if ($errors === 0) {
            $settings->set('mirror_reads_enabled', 'true');
            $progress->markCompleted($report);
            Log::info('EnableLocalDbReadJob: complete, mirror_reads_enabled=true', [
                'progress_id' => $progress->id,
                'duration_ms' => $report['_total']['duration_ms'] ?? null,
            ]);

            return;
        }

        // Partial failure : keep the flag OFF so reads stay on the safe
        // (live API) path. Persist the report regardless so the admin
        // can inspect which step broke.
        $progress->forceFill([
            'state' => MirrorBackfillProgress::STATE_FAILED,
            'completed_at' => now(),
            'report' => $report,
            'error' => "Backfill terminé avec {$errors} erreur(s) — voir le report.",
        ])->save();

        Log::warning('EnableLocalDbReadJob: completed with errors, mirror_reads_enabled stays false', [
            'progress_id' => $progress->id,
            'errors' => $errors,
        ]);
    }

    /**
     * Bus failure handler — fires when the job throws past `tries`.
     * Ensures the progress row never stays stuck in "running".
     */
    public function failed(\Throwable $e): void
    {
        $progress = MirrorBackfillProgress::query()->find($this->progressId);
        $progress?->markFailed($e);
    }
}
