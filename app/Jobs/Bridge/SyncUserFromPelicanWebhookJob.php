<?php

namespace App\Jobs\Bridge;

use App\Enums\PelicanEventKind;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mirrors a Pelican user change into the local DB.
 *
 * Triggered by Pelican outgoing webhooks for User created / updated / deleted,
 * or indirectly by SyncServerFromPelicanWebhookJob when it encounters an
 * unknown owner (always falls back to UserCreated semantics).
 *
 * Behaviour by event kind :
 *
 *   UserCreated
 *     - Refetch via API, upsert by pelican_user_id.
 *     - Controller skips this dispatch in shop_stripe mode (Shop owns
 *       creation via OAuth post-Stripe). In paymenter / disabled modes it
 *       proceeds normally.
 *     - User created without a password — Paymenter / OAuth is the canonical
 *       identity store, the customer logs in via OAuth or local reset.
 *
 *   UserUpdated
 *     - Refetch via API, update the existing row by pelican_user_id.
 *     - Runs in ALL modes including shop_stripe (covers admin email change
 *       in Pelican panel — we mirror it).
 *     - If user not found locally, falls back to upsert (legacy import case).
 *
 *   UserDeleted
 *     - No API refetch (user is gone in Pelican, would 404).
 *     - Detaches `pelican_user_id` (sets to null) — never hard-deletes the
 *       local user, who may have a Stripe sub, OAuth identities, or servers.
 *     - Runs in all modes.
 */
class SyncUserFromPelicanWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public readonly int $pelicanUserId,
        public readonly PelicanEventKind $eventKind = PelicanEventKind::UserCreated,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        match ($this->eventKind) {
            PelicanEventKind::UserDeleted => $this->handleDeletion(),
            PelicanEventKind::UserUpdated => $this->handleUpsert($pelican, isUpdate: true),
            default => $this->handleUpsert($pelican, isUpdate: false),
        };
    }

    private function handleUpsert(PelicanApplicationService $pelican, bool $isUpdate): void
    {
        try {
            $pelicanUser = $pelican->getUser($this->pelicanUserId);
        } catch (RequestException $e) {
            // 404 → user vanished between webhook send and our processing.
            // For Updated, treat as Deleted (Pelican raced ahead). For Created,
            // log + skip (no retry).
            if ($e->response?->status() === 404) {
                Log::info('SyncUserFromPelicanWebhookJob: pelican user not found, skipping', [
                    'pelican_user_id' => $this->pelicanUserId,
                    'event_kind' => $this->eventKind->value,
                ]);
                if ($isUpdate) {
                    $this->handleDeletion();
                }
                return;
            }
            throw $e;
        }

        $user = User::updateOrCreate(
            ['pelican_user_id' => $this->pelicanUserId],
            [
                'email' => strtolower(trim($pelicanUser->email)),
                'name' => $pelicanUser->name !== ''
                    ? $pelicanUser->name
                    : ($pelicanUser->username !== '' ? $pelicanUser->username : Str::before($pelicanUser->email, '@')),
                'locale' => app(SettingsService::class)->get('default_locale', 'en'),
            ],
        );

        Log::info('SyncUserFromPelicanWebhookJob: user mirrored', [
            'pelican_user_id' => $this->pelicanUserId,
            'local_user_id' => $user->id,
            'event_kind' => $this->eventKind->value,
            'was_recently_created' => $user->wasRecentlyCreated,
        ]);
    }

    private function handleDeletion(): void
    {
        $user = User::where('pelican_user_id', $this->pelicanUserId)->first();
        if ($user === null) {
            Log::info('SyncUserFromPelicanWebhookJob: pelican user delete event for unknown local user', [
                'pelican_user_id' => $this->pelicanUserId,
            ]);
            return;
        }

        // Detach only — the local user may still have a Stripe subscription,
        // OAuth identities, owned servers, etc. Hard-deleting would break
        // billing and access. Admin can finalize via /admin/users if needed.
        $user->update(['pelican_user_id' => null]);

        Log::info('SyncUserFromPelicanWebhookJob: pelican user deleted, local user detached', [
            'pelican_user_id' => $this->pelicanUserId,
            'local_user_id' => $user->id,
        ]);
    }
}
