<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Plugins\Invitations\Models\Invitation;
use Plugins\Invitations\Services\InvitationService;
use Plugins\Invitations\Services\PermissionRegistry;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Authenticated routes (server owner)
// -------------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    Route::get('servers/{serverIdentifier}/invitations', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        $invitations = Invitation::where('server_id', $server->id)
            ->active()
            ->with('inviter:id,name,email')
            ->orderByDesc('created_at')
            ->get();

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
        ]);

        // Self-invitation blocked.
        if (strtolower($validated['email']) === strtolower($request->user()->email)) {
            return response()->json(['error' => 'You cannot invite yourself.'], 422);
        }

        try {
            $service = app(InvitationService::class);
            $invitation = $service->create($server, $request->user(), $validated['email'], $validated['permissions']);

            return response()->json(['message' => 'Invitation sent.', 'id' => $invitation->id], 201);
        } catch (\RuntimeException $e) {
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

        return response()->json(['message' => 'Invitation revoked.']);
    });

    // Update a pending invitation's permissions (before it is accepted)
    Route::patch('invitations/{id}', function (int $id, Request $request): JsonResponse {
        $invitation = Invitation::with('server')->findOrFail($id);
        $server = $invitation->server;

        if (! $server || ! $request->user()->hasServerPermission($server, 'user.update')) {
            abort(403);
        }

        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            return response()->json(['error' => 'Invitation is no longer pending.'], 422);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ]);

        $invitation->update(['permissions' => $validated['permissions']]);

        return response()->json(['message' => 'Invitation updated.', 'data' => $invitation->fresh()]);
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

        try {
            $subusers = app(\Plugins\Invitations\Services\PelicanSubuserService::class)->listSubusers($serverIdentifier);
            $myEmail = strtolower($request->user()->email);

            // Pre-load the local pivot permissions for each matched user in one query.
            $emails = array_values(array_filter(array_map(
                fn (array $s): ?string => isset($s['email']) ? strtolower((string) $s['email']) : null,
                $subusers,
            )));
            $localByEmail = [];
            if (! empty($emails)) {
                $users = User::whereIn('email', $emails)->get(['id', 'email']);
                $pivots = \DB::table('server_user')
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
        } catch (\Throwable $e) {
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

        $subusers = app(\Plugins\Invitations\Services\PelicanSubuserService::class)->listSubusers($serverIdentifier);
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
            return response()->json(['error' => 'You cannot modify your own permissions.'], 403);
        }

        try {
            app(\Plugins\Invitations\Services\PelicanSubuserService::class)->updateSubuser($serverIdentifier, $subuserUuid, $validated['permissions']);

            // Sync local pivot
            if ($target && ! empty($target['email'])) {
                $localUser = User::where('email', $target['email'])->first();
                if ($localUser) {
                    \DB::table('server_user')
                        ->where('user_id', $localUser->id)
                        ->where('server_id', $server->id)
                        ->update(['permissions' => json_encode($validated['permissions']), 'updated_at' => now()]);
                }
            }

            return response()->json(['message' => 'Permissions updated.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });

    // Remove a subuser from Pelican
    Route::delete('servers/{serverIdentifier}/subusers/{subuserUuid}', function (string $serverIdentifier, string $subuserUuid, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

        if (! $request->user()->hasServerPermission($server, 'user.delete')) {
            abort(403);
        }

        $subusers = app(\Plugins\Invitations\Services\PelicanSubuserService::class)->listSubusers($serverIdentifier);
        foreach ($subusers as $sub) {
            if (($sub['uuid'] ?? '') === $subuserUuid
                && isset($sub['email'])
                && strtolower((string) $sub['email']) === strtolower($request->user()->email)) {
                return response()->json(['error' => 'You cannot remove yourself.'], 403);
            }
        }

        try {
            app(\Plugins\Invitations\Services\PelicanSubuserService::class)->deleteSubuser($serverIdentifier, $subuserUuid);

            return response()->json(['message' => 'Subuser removed.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });

    Route::get('servers/{serverIdentifier}/permissions', function (string $serverIdentifier, Request $request): JsonResponse {
        $server = Server::where('identifier', $serverIdentifier)->accessibleBy($request->user())->firstOrFail();

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
            $invitation = $service->accept($token, $request->user());

            return response()->json(['message' => 'Invitation accepted.', 'server_id' => $invitation->server_id]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });
});

// Public routes (invite landing page + registration) live in ./public.php
// so api.php stays focused on authenticated server management.
