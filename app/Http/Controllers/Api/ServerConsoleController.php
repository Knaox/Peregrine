<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\CommandRequest;
use App\Models\Server;
use App\Services\Pelican\DTOs\WebsocketCredentials;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerConsoleController extends Controller
{
    public function __construct(
        private PelicanClientService $clientService,
    ) {}

    public function command(CommandRequest $request, Server $server): JsonResponse
    {
        $command = (string) $request->validated('command');
        $this->clientService->sendCommand($server->identifier, $command);

        // Truncate the stored command — plan §S6: a malicious admin could
        // flood the audit table with multi-MB payloads otherwise.
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.command',
            server: $server,
            payload: ['command' => mb_substr($command, 0, 500)],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json(['success' => true]);
    }

    /**
     * Floor / ceiling for the per-key cache TTL. The actual TTL is computed
     * from the JWT's `exp` claim minus a 60 s safety buffer (so the SPA
     * never gets handed a token within seconds of its hard expiry). These
     * bounds protect against a malformed or unbounded `exp` value :
     *
     *   - FLOOR 60 s  : even a JWT that already expired won't bypass cache
     *                   on every hit (would re-stampede Pelican). One hit
     *                   per minute per (server, user) is the worst case.
     *   - CEILING 540 s (9 min) : matches Pelican's default JWT validity
     *                   minus the 60 s `token expiring` warning window —
     *                   any cached token outlives the SPA-side renewal
     *                   trigger, which short-circuits cache via `?fresh=1`.
     */
    private const WS_CREDENTIALS_TTL_FLOOR = 60;
    private const WS_CREDENTIALS_TTL_CEILING = 540;
    private const WS_CREDENTIALS_TTL_BUFFER = 60;

    /**
     * How long a stampede victim is willing to wait for the lock-holder to
     * publish a fresh JWT before falling back to a direct fetch. Five
     * seconds covers a slow Pelican round-trip on a busy box without
     * tying up the request thread for too long.
     */
    private const WS_CREDENTIALS_LOCK_BLOCK_SECONDS = 5;
    private const WS_CREDENTIALS_LOCK_HOLD_SECONDS = 10;

    /**
     * Stale-while-throttled backup TTL — kept WAY longer than the JWT's
     * own lifetime (~10 min) so the fallback survives Pelican throttle
     * cooldowns of any realistic length. The backup is consumed ONLY
     * when the primary cache missed AND we separately verify the
     * stored JWT's `exp` claim is still in the future, so a stale
     * entry past its crypto deadline is harmless (Wings would reject
     * it on connect anyway).
     */
    private const WS_CREDENTIALS_BACKUP_TTL = 3600;

    public function websocket(Request $request, Server $server): JsonResponse
    {
        // The WebSocket is a multi-purpose channel (console + stats). Any user
        // with server access may open it; content-level gating (console view,
        // command send, stats) is enforced by the frontend and the dedicated
        // command endpoint (which requires control.console).
        $this->authorize('view', $server);

        // SERVER-scoped cache key (NOT user-scoped). Critical at scale :
        // every Peregrine user authenticates against Pelican via the
        // SAME shared Client API key (see PelicanHttpClient and
        // Concerns\MakesClientRequests). Pelican therefore signs the
        // JWT for that single shared identity, regardless of which
        // Peregrine user triggered the fetch. Wings only validates the
        // JWT signature + the embedded user uuid against the server's
        // authorized list — both are identical across all callers, so
        // the same JWT is verbatim valid for every Peregrine user on
        // this server.
        //
        // This unlocks 1 Pelican call per ~9 min per SERVER, not per
        // (server, user) pair. With Pelican's hardcoded 5 req/min/server
        // throttle on /api/client/.../websocket
        // (`pelican-dev/panel app/Enums/ResourceLimit.php:46` — Limit::perMinute(5)
        // keyed `by($server->uuid)`), per-user keying made the 6th
        // concurrent user on the same server hit 429. Per-server keying
        // means even 5 000 simultaneous users on one server consume one
        // bucket slot every 9 minutes — orders of magnitude under the
        // limit. Permission enforcement stays user-scoped : `authorize('view')`
        // above gates fetch eligibility, the /command endpoint enforces
        // `control.console` per-user, and the SPA grays out controls a
        // user lacks. Wings doesn't enforce Peregrine permissions ; it
        // never has, with or without this change.
        $cacheKey = "peregrine:ws-creds:server-{$server->id}";
        // Long-lived "last known good" backup — survives 1 h regardless
        // of the JWT's `exp` so we can stale-fallback when Pelican
        // throttles us mid-session. Same per-server keying as primary.
        $stalePreserveKey = "peregrine:ws-creds-backup:server-{$server->id}";

        // `?fresh=1` is sent by the SPA on the `token expiring` renewal
        // path (Wings broadcasts that event ~1 min before JWT exp) — at
        // that point the cached JWT is too close to expiry to be safely
        // reused, so we MUST round-trip to Pelican for a fresh one.
        // Default behaviour (no query param) honors the cache.
        $forceFresh = $request->boolean('fresh');
        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        try {
            $credentials = $this->getCachedCredentials($cacheKey, $stalePreserveKey, $server->identifier);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Stale-while-throttled fallback. If Pelican throttles us
            // (or any 5xx) BUT we have a recently-seen JWT in the
            // long-lived backup AND that JWT is still inside its
            // crypto `exp` window, serve it instead of bubbling 429
            // to the user. Wings will accept it ; the user sees no
            // disruption while Pelican's throttle window cools down.
            //
            // Skipped on `?fresh=1` because the SPA only sets that
            // flag when it KNOWS its current JWT is about to expire :
            // serving a stale JWT here would just close the WS the
            // moment the SPA swaps it in.
            //
            // Stored as plain array (not WebsocketCredentials object)
            // because Laravel 11's default `cache.serializable_classes`
            // setting is `false` → `unserialize()` strips every custom
            // class on read, returning `__PHP_Incomplete_Class` for
            // anything we'd cache as an object. Plain arrays survive
            // intact regardless. Same rationale across the rest of the
            // ws-creds caching surface.
            if (! $forceFresh) {
                $stale = Cache::get($stalePreserveKey);
                if (is_array($stale) && isset($stale['token'], $stale['socket']) && $this->jwtStillValid($stale['token'])) {
                    \Illuminate\Support\Facades\Log::info('ws-creds: Pelican throttled, serving stale JWT from backup cache', [
                        'cache_key' => $cacheKey,
                        'pelican_status' => $e->response?->status(),
                    ]);

                    return response()->json([
                        'data' => [
                            'token' => $stale['token'],
                            'socket' => $stale['socket'],
                        ],
                    ]);
                }
            }

            // Pelican throttles its Client API by default (60/min). An admin
            // rapidly browsing multiple servers via admin mode will hit this.
            // Surface a clean status upstream instead of a 500 stacktrace.
            //
            // We tag the response with `X-Throttle-Origin: pelican` so the
            // operator can distinguish a Pelican-originated 429 from a
            // Peregrine-originated one (the route's `throttle:pelican-proxy`
            // middleware fires BEFORE this code runs, never reaches here).
            // That removes the ambiguity the operator hit on 2026-05-08 :
            // "j'ai l'impression que c'est Peregrine qui rate limite" — now
            // they can read the header and know who threw.
            $status = $e->response?->status() ?? 502;
            $code = match ($status) {
                429 => 'servers.websocket.pelican_throttled',
                403, 404 => 'servers.websocket.pelican_denied',
                default => 'servers.websocket.pelican_unavailable',
            };

            return response()
                ->json(
                    ['error' => $code, 'throttle_origin' => 'pelican'],
                    $status === 429 ? 429 : 503,
                )
                ->header('X-Throttle-Origin', 'pelican');
        }

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'server.console.stream',
            server: $server,
            payload: [],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json([
            'data' => [
                'token' => $credentials->token,
                'socket' => $credentials->socket,
            ],
        ]);
    }

    public function resources(Request $request, Server $server): JsonResponse
    {
        $this->authorize('readStats', $server);

        $resources = $this->clientService->getServerResources($server->identifier);

        return response()->json([
            'data' => [
                'state' => $resources->state,
                'cpu' => $resources->cpuAbsolute,
                'memory_bytes' => $resources->memoryBytes,
                'disk_bytes' => $resources->diskBytes,
                'network_rx' => $resources->networkRxBytes,
                'network_tx' => $resources->networkTxBytes,
            ],
        ]);
    }

    /**
     * Cached + single-flight WebSocket credentials fetch.
     *
     * Three layers of optimisation (in execution order) :
     *
     *   1. **Fast-path cache hit** — Cache::get() returns the previously
     *      cached `WebsocketCredentials`. Zero Pelican round-trip, ~1 ms.
     *
     *   2. **Single-flight via Cache::lock** — on a cache miss, only the
     *      first concurrent request acquires the lock and round-trips to
     *      Pelican. Up to N-1 sibling requests for the same key block
     *      on the lock, then read the warmed cache. This is the
     *      thundering-herd guard that matters at 1 000-user scale : a
     *      cold cache + simultaneous WS reconnects from 1 000 tabs of
     *      the same user × server pair would otherwise fan out to 1 000
     *      Pelican calls in milliseconds. With the lock, that collapses
     *      to ONE call.
     *
     *   3. **JWT-aware TTL** — instead of a fixed window, we parse the
     *      JWT's `exp` claim and cache until 60 s before expiry. Wins
     *      ~80% extra cache lifetime over the previous 5 min flat cap
     *      (Pelican typically issues 10-15 min JWTs), which means a
     *      typical 1 000-user fleet bursts ONE Pelican call per
     *      (server, user) pair every ~9 min instead of every 5 min.
     *
     * Capacity math for 1 000 active users (each browsing 5 servers) :
     *
     *   - Distinct cache keys : 1 000 × 5 = 5 000
     *   - JWT TTL ≈ 9 min     : ~5 000 / 9 ≈ 555 Pelican calls / minute
     *     spread over time as cache entries expire on a rolling window.
     *   - Pelican Client API throttle (operator's 500 000 / min) : 1 000×
     *     headroom on the resulting load. 0 % chance of 429 even under
     *     full panel-wide reconnect storms.
     *
     * Lock fallback : if the lock holder is stuck on a slow Pelican call
     * for > 5 s, we drop down to a direct fetch instead of timing out
     * the user's request. Worse than cached but never broken UX.
     */
    private function getCachedCredentials(string $cacheKey, string $stalePreserveKey, string $serverIdentifier): WebsocketCredentials
    {
        // Cache stores plain arrays, NOT WebsocketCredentials objects :
        // Laravel 11+ defaults `cache.serializable_classes` to `false`,
        // which forces `unserialize()` to use `allowed_classes => false`.
        // Any cached object is restored as `__PHP_Incomplete_Class` and
        // would silently fail every type check downstream — observed
        // 2026-05-08 with the per-user cache, where the entire cache
        // layer was a no-op (every request hit Pelican). Arrays bypass
        // that restriction by definition.

        // Fast path : cache already warm. The vast majority of hits in
        // production land here — no lock acquisition, no Pelican call.
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['token'], $cached['socket'])) {
            return new WebsocketCredentials(token: $cached['token'], socket: $cached['socket']);
        }

        // Slow path : single-flight lock. Across N concurrent requests
        // for the same cache key, only one acquires the lock ; the
        // others block until either (a) the lock-holder publishes the
        // cache entry → siblings read the warm cache, or (b) the block
        // timeout fires → siblings fall back to a direct fetch (still
        // correct, just suboptimal under extreme contention).
        $lock = Cache::lock("peregrine:ws-creds-lock:{$cacheKey}", self::WS_CREDENTIALS_LOCK_HOLD_SECONDS);

        try {
            return $lock->block(self::WS_CREDENTIALS_LOCK_BLOCK_SECONDS, function () use ($cacheKey, $stalePreserveKey, $serverIdentifier) {
                // Re-check under the lock — sibling requests that woke
                // us by releasing the lock have already published the
                // cache entry. Reading it here saves another Pelican
                // round-trip and makes the lock truly single-flight.
                $maybe = Cache::get($cacheKey);
                if (is_array($maybe) && isset($maybe['token'], $maybe['socket'])) {
                    return new WebsocketCredentials(token: $maybe['token'], socket: $maybe['socket']);
                }

                $credentials = $this->clientService->getWebsocket($serverIdentifier);
                $payload = ['token' => $credentials->token, 'socket' => $credentials->socket];

                Cache::put($cacheKey, $payload, $this->resolveJwtTtl($credentials->token));
                // Long-lived backup ("stale cache") for the
                // throttle-fallback path in websocket() : 1 h is well
                // beyond any realistic Pelican-throttle cooldown
                // window, so we always have a "last known good" JWT
                // to serve when Pelican rate-limits the next fetch.
                // We don't honor TTL bounds here ; this entry is
                // ONLY consumed if the primary cache missed AND we
                // separately verify `exp` is still in the future.
                Cache::put($stalePreserveKey, $payload, self::WS_CREDENTIALS_BACKUP_TTL);

                return $credentials;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            // Lock-holder got stuck — direct fetch as last resort. The
            // resulting JWT is still valid ; we just lose the
            // single-flight benefit on this one request. Logging at
            // info because it's a degraded-but-functional state.
            \Illuminate\Support\Facades\Log::info('ws-creds: lock timeout, falling back to direct Pelican fetch', [
                'cache_key' => $cacheKey,
            ]);

            $credentials = $this->clientService->getWebsocket($serverIdentifier);
            $payload = ['token' => $credentials->token, 'socket' => $credentials->socket];
            Cache::put($cacheKey, $payload, $this->resolveJwtTtl($credentials->token));
            Cache::put($stalePreserveKey, $payload, self::WS_CREDENTIALS_BACKUP_TTL);

            return $credentials;
        }
    }

    /**
     * True if the JWT's `exp` claim is at least 30 s in the future.
     * Conservative window because a stale-served JWT must still be
     * usable by the time the browser opens its WS connection (network
     * round-trip + Wings auth). Less than 30 s and we'd hand out a
     * token that expires mid-handshake.
     */
    private function jwtStillValid(string $jwt): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        $padded = str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
        $rawPayload = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($rawPayload === false) {
            return false;
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload) || ! isset($payload['exp']) || ! is_numeric($payload['exp'])) {
            return false;
        }

        return (int) $payload['exp'] > now()->timestamp + 30;
    }

    /**
     * Compute cache TTL from the JWT's `exp` claim minus a 60 s safety
     * buffer. Falls back to the 60 s floor when the JWT is malformed or
     * the claim is missing — never trusts an unbounded value.
     *
     * The buffer matches Wings' own `token expiring` warning window so
     * the SPA's renewal flow (which bypasses cache via `?fresh=1`) lands
     * BEFORE the cached entry could leak a near-dead JWT to a sibling.
     */
    private function resolveJwtTtl(string $jwt): int
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return self::WS_CREDENTIALS_TTL_FLOOR;
        }

        // base64url → base64 → decode → JSON. Standard JWT layout ;
        // doesn't validate the signature (we don't own Pelican's key
        // and don't need to verify here — Wings does that on connect).
        $padded = str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
        $rawPayload = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($rawPayload === false) {
            return self::WS_CREDENTIALS_TTL_FLOOR;
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload) || ! isset($payload['exp']) || ! is_numeric($payload['exp'])) {
            return self::WS_CREDENTIALS_TTL_FLOOR;
        }

        $remaining = (int) $payload['exp'] - now()->timestamp - self::WS_CREDENTIALS_TTL_BUFFER;

        return max(
            self::WS_CREDENTIALS_TTL_FLOOR,
            min(self::WS_CREDENTIALS_TTL_CEILING, $remaining),
        );
    }
}
