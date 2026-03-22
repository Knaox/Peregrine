<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\SftpPasswordRequest;
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
        $user->update($request->validated());

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
            'password' => $request->validated('password'),
        ]);

        return response()->json(['success' => true]);
    }
}
