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
            return response()->json(['error' => 'Invitation not found.'], 404);
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

        return response()->json([
            'email' => $invitation->email,
            'server_name' => $invitation->server?->name,
            'inviter_name' => $invitation->inviter?->name,
            'permissions' => $permissionLabels,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'is_active' => $invitation->isActive(),
            'is_accepted' => $invitation->accepted_at !== null,
            'is_revoked' => $invitation->revoked_at !== null,
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
            return response()->json(['error' => 'Invitation not found or expired.'], 404);
        }

        if (strtolower($validated['email']) !== strtolower($invitation->email)) {
            return response()->json(['error' => 'Email does not match the invitation.'], 422);
        }

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json(['error' => 'An account with this email already exists. Please log in.'], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $service = app(InvitationService::class);
            $service->accept($token, $user);

            return response()->json(['message' => 'Account created and invitation accepted.'], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    });
});
