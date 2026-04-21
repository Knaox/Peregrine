<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TwoFactorChallengeStore;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        TwoFactorChallengeStore $challenges,
    ): JsonResponse {
        $validated = $request->validated();

        if (!Auth::attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $validated['remember'] ?? false,
        )) {
            return response()->json(['message' => 'auth.login.error'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->hasTwoFactor()) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $challengeId = $challenges->put($user->id, [
                'type' => 'password',
                'provider' => null,
                'intended_url' => null,
            ]);

            return response()->json([
                'requires_2fa' => true,
                'challenge_id' => $challengeId,
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function register(RegisterRequest $request, SettingsService $settings): JsonResponse
    {
        $localRegistrationEnabled = $settings->get('auth_local_registration_enabled', 'true') === 'true';

        if (! $localRegistrationEnabled) {
            return response()->json(['message' => 'auth.register.disabled'], 403);
        }

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => $validated['locale'] ?? 'en',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['user' => null]);
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
