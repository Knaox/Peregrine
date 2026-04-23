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
        // uses the email for SFTP notifications / account recovery. Don't
        // fail the profile save on a Pelican outage, but DO log so the
        // desync is visible — `sync:health` confirms drift on the next run.
        if (isset($validated['email'])
            && $validated['email'] !== $oldEmail
            && $user->pelican_user_id !== null
        ) {
            try {
                $this->pelicanService->changeUserEmail($user->pelican_user_id, $validated['email']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Pelican email sync failed during profile update', [
                    'user_id' => $user->id,
                    'pelican_user_id' => $user->pelican_user_id,
                    'new_email' => $validated['email'],
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'response_body' => method_exists($e, 'response') && $e->response ? (string) $e->response->body() : null,
                ]);
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
