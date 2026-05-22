<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Plugins\EasyConfiguration\Models\CopyLog;
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
    ) {}

    public function handle(CopyService $copy): void
    {
        $source = Server::find($this->sourceServerId);
        if ($source === null) {
            return;
        }

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
                CopyLog::create([...$base, 'status' => 'success', 'params_count' => $count]);
            } catch (Throwable $e) {
                CopyLog::create([...$base, 'status' => 'failed', 'error' => $e->getMessage(), 'params_count' => 0]);
            }
        }
    }
}
