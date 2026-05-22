<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Plugins\EasyConfiguration\Models\BoostSchedule;
use Plugins\EasyConfiguration\Services\Boost\BoostApplier;
use Throwable;

/**
 * Ends an active boost (naturally expired -> "completed", or cancelled ->
 * "cancelled"): stop the server, restore the original baselines, archive to
 * history, restart. No-op if the boost is no longer active.
 */
final class EndBoostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $boostId, public readonly string $finalStatus = 'completed') {}

    public function handle(BoostApplier $applier): void
    {
        $boost = BoostSchedule::find($this->boostId);
        if ($boost === null || $boost->status !== 'active') {
            return;
        }

        try {
            $applier->end($boost, $this->finalStatus);
        } catch (Throwable $e) {
            Log::error('easy-config: ending a boost failed', ['boost' => $this->boostId, 'message' => $e->getMessage()]);
        }
    }
}
