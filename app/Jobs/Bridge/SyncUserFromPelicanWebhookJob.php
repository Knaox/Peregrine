<?php

namespace App\Jobs\Bridge;

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
 * Mirrors a Pelican user into the local DB (Bridge Paymenter mode).
 *
 * Triggered when Pelican fires `eloquent.created: App\Models\User`, or
 * indirectly by SyncServerFromPelicanWebhookJob when it encounters an
 * unknown owner.
 *
 * The user is created without a password — Paymenter is the canonical
 * identity store in this mode, the customer logs into Peregrine via
 * Paymenter OAuth (or asks for a local reset) but never receives a
 * "welcome" email from Peregrine. Paymenter handles all customer comms.
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
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        try {
            $pelicanUser = $pelican->getUser($this->pelicanUserId);
        } catch (RequestException $e) {
            // 404 → user was deleted between webhook send and our processing.
            // Log once, do not retry — there's nothing to mirror.
            if ($e->response?->status() === 404) {
                Log::info('SyncUserFromPelicanWebhookJob: pelican user not found, skipping', [
                    'pelican_user_id' => $this->pelicanUserId,
                ]);
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
        ]);
    }
}
