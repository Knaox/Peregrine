<?php

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Plugins\Invitations\Models\Invitation;
use Plugins\Invitations\Services\InvitationService;
use Plugins\Invitations\Services\PermissionRegistry;

// Public invitation routes (rate limited). No authentication required:
// these are the landing endpoints when a recipient clicks the link in their email.
Route::middleware('throttle:30,1')->group(function () {

    Route::get('invite/{token}', function (string $token): JsonResponse {
        $hashedToken = hash('sha256', $token);
        $invitation = Invitation::with('server:id,name,identifier', 'inviter:id,name')
            ->where('token', $hashedToken)
            ->first();

        if (! $invitation) {
            return response()->json(['error' => __('invitations::messages.errors.invalid_token'), 'error_code' => 'invalid_token'], 404);
        }

        $locale = request()->header('Accept-Language', 'en');
        $locale = str_starts_with($locale, 'fr') ? 'fr' : 'en';

        $registry = app(PermissionRegistry::class);
        $allGroups = $registry->getGroups();
        $permissionLabels = [];

        foreach ($invitation->permissions ?? [] as $permKey) {
            foreach ($allGroups as $group) {
                if (isset($group['permissions'][$permKey])) {
                    $permissionLabels[] = [
                        'key' => $permKey,
                        'label' => $group['permissions'][$permKey][$locale] ?? $group['permissions'][$permKey]['en'] ?? $permKey,
                    ];

                    break;
                }
            }
        }

        // Every server this link grants access to: the whole batch when the
        // invitation is part of a multi-server batch, otherwise just this one.
        $servers = $invitation->batch_id
            ? Invitation::where('batch_id', $invitation->batch_id)
                ->with('server:id,name')
                ->get()
                ->map(fn (Invitation $inv): ?string => $inv->server?->name)
                ->filter()
                ->values()
                ->all()
            : array_values(array_filter([$invitation->server?->name]));

        return response()->json([
            'email' => $invitation->email,
            'server_name' => $invitation->server?->name,
            'servers' => $servers,
            'server_count' => count($servers),
            'inviter_name' => $invitation->inviter?->name,
            'permissions' => $permissionLabels,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'is_active' => $invitation->isActive(),
            'is_accepted' => $invitation->accepted_at !== null,
            'is_revoked' => $invitation->revoked_at !== null,
            // Lets the accept page skip the register form and send a known
            // account straight to login instead of failing on submit (422).
            // Case-insensitive: the host does NOT normalise emails (OAuth/shop
            // accounts keep their original casing), so an exact match would miss
            // an existing mixed-case account and wrongly push the invitee — who
            // already has an account — into the register flow.
            'user_exists' => User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $invitation->email))])->exists(),
        ]);
    });

    Route::post('invite/{token}/register', function (string $token, Request $request): JsonResponse {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        $hashedToken = hash('sha256', $token);
        $invitation = Invitation::active()->where('token', $hashedToken)->first();

        if (! $invitation) {
            return response()->json(['error' => __('invitations::messages.errors.invitation_not_found'), 'error_code' => 'invitation_not_found'], 404);
        }

        if (strtolower($validated['email']) !== strtolower($invitation->email)) {
            return response()->json(['error' => __('invitations::messages.errors.email_mismatch'), 'error_code' => 'email_mismatch'], 422);
        }

        // Case-insensitive existence guard — emails aren't normalised host-side,
        // so an exact match could let a duplicate (mixed-case) account through.
        if (User::whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->exists()) {
            return response()->json(['error' => __('invitations::messages.errors.account_exists'), 'error_code' => 'account_exists'], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $service = app(InvitationService::class);
            $service->accept($token, $user);

            return response()->json(['message' => __('invitations::messages.success.account_created_and_accepted')], 201);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            // Pelican client calls throw RequestException, not RuntimeException.
            // The account was created; surface a clean error so the user can log
            // in and accept again rather than getting an opaque 500.
            report($e);

            return response()->json(['error' => __('invitations::messages.errors.accept_failed')], 422);
        }
    });
});
