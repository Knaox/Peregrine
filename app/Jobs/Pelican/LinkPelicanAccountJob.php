<?php

namespace App\Jobs\Pelican;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ensure a Peregrine User has a linked Pelican account, queued.
 *
 * Wraps `EnsurePelicanAccountAction` so the work happens off the request
 * thread — registrations, OAuth callbacks, and Stripe webhooks must NOT
 * block on Pelican availability. The action itself is idempotent, so retry
 * after a Pelican outage is safe.
 *
 * Retry policy is generous (5 tries up to ~1h backoff) because Pelican
 * downtime should not orphan a paying customer.
 *
 * `ShouldBeUnique` (key = user_id) prevents duplicate work when several
 * entry points fire near-simultaneously for the same user (e.g. OAuth
 * callback that also triggers a login backfill).
 */
final class LinkPelicanAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800, 3600];

    public int $timeout = 30;

    /**
     * Lock release window: 60 seconds is enough for a healthy Pelican to
     * answer twice, short enough that a stuck worker doesn't block a retry.
     */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $source = 'unknown',
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(EnsurePelicanAccountAction $action): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('LinkPelicanAccountJob: user vanished, skipping', [
                'user_id' => $this->userId,
                'source' => $this->source,
            ]);
            return;
        }

        $action->execute($user, $this->source);
    }
}
