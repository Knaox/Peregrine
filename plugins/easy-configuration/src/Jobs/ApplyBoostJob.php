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
 * Applies a due pending boost: stop the server, write the capped boosted values,
 * flip it active, restart. No-op if the boost was cancelled before it ran.
 */
final class ApplyBoostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $boostId) {}

    public function handle(BoostApplier $applier): void
    {
        $boost = BoostSchedule::find($this->boostId);
        if ($boost === null || $boost->status !== 'pending') {
            return;
        }

        try {
            $applier->apply($boost);
        } catch (Throwable $e) {
            Log::error('easy-config: applying a boost failed', ['boost' => $this->boostId, 'message' => $e->getMessage()]);
        }
    }
}
