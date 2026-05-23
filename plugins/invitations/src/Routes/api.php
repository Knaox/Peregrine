<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Plugins\Invitations\Models\Invitation;
use Plugins\Invitations\Services\InvitationService;
use Plugins\Invitations\Services\PelicanSubuserService;
use Plugins\Invitations\Services\PermissionRegistry;

// -------------------------------------------------------------------------
// Authenticated routes (server owner)
// -------------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    Route::get('servers/{serverIdentifier}/invitations', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.read')) {
            abort(403);
        }

        $invitations = Invitation::where('server_id', $server->id)
            ->active()
            ->with('inviter:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        // Attach how many servers each batch spans so the UI can badge a
        // multi-server invite. Single invites (batch_id null) stay null.
        $batchIds = $invitations->pluck('batch_id')->filter()->unique()->values();
        $sizes = $batchIds->isEmpty()
            ? collect()
            : Invitation::whereIn('batch_id', $batchIds->all())
                ->selectRaw('batch_id, COUNT(*) as aggregate')
                ->groupBy('batch_id')
                ->pluck('aggregate', 'batch_id');
        $invitations->each(function (Invitation $inv) use ($sizes): void {
            $inv->batch_size = $inv->batch_id ? (int) ($sizes[$inv->batch_id] ?? 1) : null;
        });

        return response()->json(['data' => $invitations]);
    });

    Route::post('servers/{serverIdentifier}/invitations', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.create')) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
            // Optional extra servers — invite the same user to several servers
            // in ONE email whose accept link authorizes them all at once.
            'server_ids' => ['sometimes', 'array'],
            'server_ids.*' => ['integer', 'distinct', 'exists:servers,id'],
        ]);

        // Self-invitation blocked.
        if (strtolower($validated['email']) === strtolower($request->user()->email)) {
            return response()->json(['error' => __('invitations::messages.errors.self_invite'), 'error_code' => 'self_invite'], 422);
        }

        try {
            $service = app(InvitationService::class);

            // No extra servers → classic single-server invite (unchanged contract).
            if (empty($validated['server_ids'])) {
                $invitation = $service->create($server, $request->user(), $validated['email'], $validated['permissions']);

                return response()->json(['message' => __('invitations::messages.success.invitation_sent'), 'id' => $invitation->id], 201);
            }

            // Targets are exactly the servers the client selected and the
            // inviter may create users on (unauthorized ones dropped). The
            // current server is included by the client only when it should be
            // (a fresh invite — never a copy-access). Restricted to the SAME
            // egg as the current server: permissions are egg-specific, so a
            // different egg would receive an invalid permission set.
            $targetIds = Server::whereIn('id', collect($validated['server_ids'])->unique()->values()->all())
                ->get()
                ->filter(fn (Server $s): bool => $request->user()->hasServerPermission($s, 'user.create')
                    && $s->egg_id === $server->egg_id)
                ->pluck('id')
                ->all();

            if (empty($targetIds)) {
                abort(403);
            }

            $result = $service->createBatch($targetIds, $request->user(), $validated['email'], $validated['permissions']);

            return response()->json([
                'message' => __('invitations::messages.success.invitation_sent'),
                'ids' => collect($result['invitations'])->pluck('id')->all(),
                'skipped' => $result['skipped'],
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });

    Route::delete('invitations/{id}', function (int $id, Request $request): JsonResponse {
        $invitation = Invitation::with('server')->findOrFail($id);
        $server = $invitation->server;

        if (! $server || ! $request->user()->hasServerPermission($server, 'user.update')) {
            abort(403);
        }

        app(InvitationService::class)->revoke($invitation);

        return response()->json(['message' => __('invitations::messages.success.invitation_revoked')]);
    });

    // Re-send the email of a pending invitation.
    //
    // Resendable iff the invitation is still in flight (not accepted,
    // not revoked). Token is rotated on every resend so any older mail
    // still sitting in a mailbox is invalidated — same security posture
    // as a Laravel password reset. Throttled at 5 / min / user via the
    // `invitation-resend` rate limiter (registered in
    // InvitationsServiceProvider) so one operator can't spam an invitee.
    Route::post('invitations/{id}/resend', function (int $id, Request $request): JsonResponse {
        $invitation = Invitation::with('server')->findOrFail($id);
        $server = $invitation->server;

        if (! $server || ! $request->user()->hasServerPermission($server, 'user.create')) {
            abort(403);
        }

        try {
            app(InvitationService::class)->resend($invitation);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('invitations::messages.success.invitation_resent'), 'data' => $invitation->fresh()]);
    })->middleware('throttle:invitation-resend');

    // Update a pending invitation's permissions (before it is accepted)
    Route::patch('invitations/{id}', function (int $id, Request $request): JsonResponse {
        $invitation = Invitation::with('server')->findOrFail($id);
        $server = $invitation->server;

        if (! $server || ! $request->user()->hasServerPermission($server, 'user.update')) {
            abort(403);
        }

        if ($invitation->accepted_at !== null) {
            return response()->json(['error' => __('invitations::messages.errors.already_accepted'), 'error_code' => 'already_accepted'], 422);
        }
        if ($invitation->revoked_at !== null) {
            return response()->json(['error' => __('invitations::messages.errors.already_revoked'), 'error_code' => 'already_revoked'], 422);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ]);

        $invitation->update(['permissions' => $validated['permissions']]);

        return response()->json(['message' => __('invitations::messages.success.invitation_updated'), 'data' => $invitation->fresh()]);
    });

    // List existing subusers from Pelican. Each row is marked `is_current_user`
    // so the frontend can hide self-management controls.
    //
    // Merges Peregrine-specific keys (overview.*, any plugin-registered keys)
    // from server_user.permissions pivot into the Pelican response. Pelican
    // silently drops unknown permission keys, so the local pivot is the
    // authoritative source for the full permission set.
    Route::get('servers/{serverIdentifier}/subusers', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.read')) {
            abort(403);
        }

        try {
            $subusers = app(PelicanSubuserService::class)->listSubusers($serverIdentifier);
            $myEmail = strtolower($request->user()->email);

            // Pre-load the local pivot permissions for each matched user in one query.
            $emails = array_values(array_filter(array_map(
                fn (array $s): ?string => isset($s['email']) ? strtolower((string) $s['email']) : null,
                $subusers,
            )));
            $localByEmail = [];
            if (! empty($emails)) {
                // Case-insensitive match: the host does NOT normalise emails
                // (OAuth/shop accounts keep their original casing), so an exact
                // whereIn('email', …) silently misses them and the local pivot
                // permissions never get merged in — the granted permissions look
                // like they "didn't apply". $emails is already lowercased.
                $users = User::whereIn(DB::raw('LOWER(email)'), $emails)->get(['id', 'email']);
                $pivots = DB::table('server_user')
                    ->whereIn('user_id', $users->pluck('id'))
                    ->where('server_id', $server->id)
                    ->get(['user_id', 'permissions']);
                $pivotByUserId = $pivots->keyBy('user_id');
                foreach ($users as $u) {
                    $pivot = $pivotByUserId->get($u->id);
                    if ($pivot && $pivot->permissions) {
                        $raw = is_string($pivot->permissions) ? json_decode($pivot->permissions, true) : $pivot->permissions;
                        $localByEmail[strtolower($u->email)] = is_array($raw) ? $raw : [];
                    }
                }
            }

            $subusers = array_map(function (array $sub) use ($myEmail, $localByEmail): array {
                $sub['is_current_user'] = isset($sub['email'])
                    && strtolower((string) $sub['email']) === $myEmail;

                $localEmail = isset($sub['email']) ? strtolower((string) $sub['email']) : null;
                $localPerms = $localEmail ? ($localByEmail[$localEmail] ?? []) : [];
                $pelicanPerms = isset($sub['permissions']) && is_array($sub['permissions']) ? $sub['permissions'] : [];
                $sub['permissions'] = array_values(array_unique(array_merge($pelicanPerms, $localPerms)));

                return $sub;
            }, $subusers);

            return response()->json(['data' => $subusers]);
        } catch (Throwable $e) {
            return response()->json(['data' => [], 'error' => $e->getMessage()]);
        }
    });

    // Update subuser permissions on Pelican + local pivot
    Route::post('servers/{serverIdentifier}/subusers/{subuserUuid}', function (string $serverIdentifier, string $subuserUuid, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.update')) {
            abort(403);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ]);

        $subusers = app(PelicanSubuserService::class)->listSubusers($serverIdentifier);
        $target = null;
        foreach ($subusers as $sub) {
            if (($sub['uuid'] ?? '') === $subuserUuid) {
                $target = $sub;
                break;
            }
        }

        // Self-protection: a subuser cannot edit their own permissions.
        if ($target && isset($target['email'])
            && strtolower((string) $target['email']) === strtolower($request->user()->email)) {
            return response()->json(['error' => __('invitations::messages.errors.self_modify'), 'error_code' => 'self_modify'], 403);
        }

        try {
            app(PelicanSubuserService::class)->updateSubuser($serverIdentifier, $subuserUuid, $validated['permissions']);

            // Sync local pivot. Case-insensitive match — the host does not
            // normalise emails, so where('email', …) misses mixed-case accounts
            // and the new permissions would never reach the pivot.
            if ($target && ! empty($target['email'])) {
                $localUser = User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $target['email']))])->first();
                if ($localUser) {
                    DB::table('server_user')
                        ->where('user_id', $localUser->id)
                        ->where('server_id', $server->id)
                        ->update(['permissions' => json_encode($validated['permissions']), 'updated_at' => now()]);
                }
            }

            return response()->json(['message' => __('invitations::messages.success.permissions_updated')]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });

    // Remove a subuser: drop them from Pelican AND clean up Peregrine's local
    // access. Without the local cleanup the user keeps every permission via the
    // server_user pivot (permissionsForUser reads it), so they'd stay "revoked
    // but still in" — a security hole.
    Route::delete('servers/{serverIdentifier}/subusers/{subuserUuid}', function (string $serverIdentifier, string $subuserUuid, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.delete')) {
            abort(403);
        }

        // Resolve the target's email from Pelican BEFORE deletion so we can
        // clean up local access. Best-effort: a throttled / failing list must
        // NOT abort the removal — the local revocation below is the part that
        // actually strips access in Peregrine.
        $targetEmail = null;
        try {
            foreach (app(PelicanSubuserService::class)->listSubusers($serverIdentifier) as $sub) {
                if (($sub['uuid'] ?? '') === $subuserUuid) {
                    $targetEmail = isset($sub['email']) ? strtolower(trim((string) $sub['email'])) : null;
                    break;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        // A subuser cannot remove themselves.
        if ($targetEmail !== null && $targetEmail === strtolower($request->user()->email)) {
            return response()->json(['error' => __('invitations::messages.errors.self_remove'), 'error_code' => 'self_remove'], 403);
        }

        try {
            app(PelicanSubuserService::class)->deleteSubuser($serverIdentifier, $subuserUuid);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Revoke Peregrine-side access too: drop the pivot and close any
        // lingering invitation so the user truly loses access and can be cleanly
        // re-invited later. Without this the user keeps every permission via the
        // server_user pivot (ServerPolicy / permissionsForUser read it) — i.e.
        // "revoked but still in".
        //
        // Match case-insensitively: the host does NOT normalise emails
        // (OAuth/shop accounts keep their original casing), so an exact
        // where('email', …) silently misses them and the pivot survives — which
        // is exactly how a removed user kept their access. Mirrors the
        // LOWER(email) lookups the host uses in SocialAuthService.
        if ($targetEmail !== null) {
            $localUser = User::whereRaw('LOWER(email) = ?', [$targetEmail])->first();
            if ($localUser) {
                DB::table('server_user')
                    ->where('user_id', $localUser->id)
                    ->where('server_id', $server->id)
                    ->delete();
            }
            Invitation::where('server_id', $server->id)
                ->whereRaw('LOWER(email) = ?', [$targetEmail])
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'Subuser removed.']);
    });

    Route::get('servers/{serverIdentifier}/permissions', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.read')) {
            abort(403);
        }

        $locale = $request->header('Accept-Language', 'en');
        $locale = str_starts_with($locale, 'fr') ? 'fr' : 'en';

        // Per-server filtering : groups can declare an `availableForServer`
        // closure (see PermissionRegistry::registerGroup) so per-egg plugins
        // like egg-config-editor only surface their permissions when the
        // server's egg actually has data for them. Groups with no filter
        // remain always-visible (every native Pelican group).
        return response()->json(['data' => PermissionRegistry::getInstance()->toArrayForServer($locale, $server)]);
    });

    // Accept invitation (authenticated user)
    Route::post('invite/{token}/accept', function (string $token, Request $request): JsonResponse {
        try {
            $service = app(InvitationService::class);
            $result = $service->accept($token, $request->user());

            return response()->json([
                'message' => 'Invitation accepted.',
                'server_id' => $result['first_server_id'],
                'server_ids' => $result['accepted'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            // Pelican client calls throw RequestException (not RuntimeException);
            // without this catch the accept 500s and the invite stays "pending".
            // Report for diagnosis, surface a clean error so the user can retry.
            report($e);

            return response()->json(['error' => __('invitations::messages.errors.accept_failed')], 422);
        }
    });
});

// Public routes (invite landing page + registration) live in ./public.php
// so api.php stays focused on authenticated server management.
