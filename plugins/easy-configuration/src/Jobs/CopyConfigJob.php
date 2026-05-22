<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Plugins\EasyConfiguration\Models\BoostSchedule;
use Plugins\EasyConfiguration\Models\CopyLog;
use Plugins\EasyConfiguration\Services\Boost\BoostService;
use Plugins\EasyConfiguration\Services\Copy\CopyService;
use Throwable;

/**
 * Copies selected config parameters from a source server to one or more target
 * servers in the background. Writes one CopyLog row per target as it goes, so
 * the UI can poll the batch for a per-server recap. No distributed rollback —
 * successful targets are kept and failures are reported individually.
 */
final class CopyConfigJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<int>  $targetIds
     * @param  list<array{id: string, params: list<array{key: string, section?: string|null}>}>  $files
     */
    public function __construct(
        public readonly string $batchId,
        public readonly int $sourceServerId,
        public readonly array $targetIds,
        public readonly array $files,
        public readonly ?int $userId,
        public readonly bool $copyBoosts = false,
    ) {}

    public function handle(CopyService $copy, BoostService $boosts): void
    {
        $source = Server::find($this->sourceServerId);
        if ($source === null) {
            return;
        }

        $sourceBoosts = $this->copyBoosts
            ? BoostSchedule::query()->where('server_id', $source->id)->live()->get()
            : collect();

        foreach ($this->targetIds as $targetId) {
            $base = [
                'batch_id' => $this->batchId,
                'source_server_id' => $this->sourceServerId,
                'target_server_id' => $targetId,
                'files' => $this->files,
                'created_by' => $this->userId,
            ];

            $target = Server::find($targetId);
            if ($target === null) {
                CopyLog::create([...$base, 'status' => 'failed', 'error' => 'Target server not found', 'params_count' => 0]);

                continue;
            }

            try {
                $count = $copy->copy($source, $target, $this->files);
                $copiedBoosts = $this->copyBoosts && $this->duplicateBoosts($boosts, $target, $sourceBoosts);
                CopyLog::create([...$base, 'status' => 'success', 'params_count' => $count, 'copied_boosts' => $copiedBoosts]);
            } catch (Throwable $e) {
                CopyLog::create([...$base, 'status' => 'failed', 'error' => $e->getMessage(), 'params_count' => 0]);
            }
        }
    }

    /**
     * Duplicate the source's live boosts onto a target (absolute dates kept,
     * baselines dropped — the target recomputes its own). Overlapping ones are
     * skipped. Returns true if at least one boost was created.
     *
     * @param  Collection<int, BoostSchedule>  $sourceBoosts
     */
    private function duplicateBoosts(BoostService $boosts, Server $target, $sourceBoosts): bool
    {
        $copied = false;

        foreach ($sourceBoosts as $boost) {
            $params = array_map(static fn (array $param): array => [
                'file_id' => $param['file_id'],
                'section' => $param['section'] ?? null,
                'key' => $param['key'],
                'max_cap' => $param['max_cap'] ?? null,
            ], $boost->parameters);

            try {
                $boosts->create($target, $boost->template_id, (float) $boost->multiplier, $boost->start_at, $boost->end_at, $params, $this->userId);
                $copied = true;
            } catch (Throwable) {
                // Overlapping boost on the target — skip it.
            }
        }

        return $copied;
    }
}
