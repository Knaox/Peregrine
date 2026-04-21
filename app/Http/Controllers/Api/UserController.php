<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\SftpPasswordRequest;
use App\Http\Requests\User\UpdateDashboardLayoutRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldEmail = $user->email;

        $user->update($validated);

        // Propagate email changes to Pelican so the two stay in sync. Pelican
        // uses the email for SFTP notifications / account recovery. Silent on
        // failure so a temporary Pelican outage doesn't break the profile
        // save — next successful sync closes the gap.
        if (isset($validated['email'])
            && $validated['email'] !== $oldEmail
            && $user->pelican_user_id !== null
        ) {
            try {
                $this->pelicanService->updateUser($user->pelican_user_id, [
                    'email' => $validated['email'],
                ]);
            } catch (\Throwable) {
                // Log and move on — see `sync:health` to surface drift.
            }
        }

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json(['success' => true]);
    }

    public function getDashboardLayout(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->dashboard_layout,
        ]);
    }

    public function updateDashboardLayout(UpdateDashboardLayoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['dashboard_layout' => $request->validated('layout')]);

        return response()->json([
            'data' => $user->dashboard_layout,
        ]);
    }

    public function sftpPassword(SftpPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pelican_user_id) {
            return response()->json([
                'success' => false,
                'error' => 'No Pelican account linked.',
            ], 422);
        }

        $this->pelicanService->updateUser($user->pelican_user_id, [
            'email' => $user->email,
            'username' => $user->name,
            'password' => $request->validated('password'),
        ]);

        return response()->json(['success' => true]);
    }
}
