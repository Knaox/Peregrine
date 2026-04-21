<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Http\Requests\Auth\TwoFactorDisableRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TwoFactorChallengeStore;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly TwoFactorChallengeStore $challenges,
    ) {}

    /**
     * Generate a fresh secret + QR + otpauth URI. The secret is NOT persisted
     * — the client echoes it back to /confirm along with a code to activate.
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $this->twoFactor->generateSecret($user);

        return response()->json([
            'secret' => $secret,
            'qr_svg_base64' => $this->twoFactor->qrCodeSvg($user, $secret),
            'otpauth_uri' => $this->twoFactor->otpauthUri($user, $secret),
        ]);
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return response()->json(['error' => 'auth.2fa.already_enabled'], 409);
        }

        $recoveryCodes = $this->twoFactor->verifyAndActivate(
            $user,
            $request->string('secret')->toString(),
            $request->string('code')->toString(),
        );

        if ($recoveryCodes === []) {
            return response()->json(['error' => 'auth.2fa.invalid_code'], 422);
        }

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    /**
     * Unauthenticated route. Reads {challenge_id, code} from the request,
     * looks up the pending user in Redis, verifies TOTP or recovery code,
     * logs in on success, returns the authenticated user.
     */
    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $challengeId = $request->string('challenge_id')->toString();
        $code = $request->string('code')->toString();

        $state = $this->challenges->get($challengeId);

        if ($state === null) {
            return response()->json(['error' => 'auth.2fa.challenge_expired'], 410);
        }

        $user = User::find($state['user_id']);

        if ($user === null) {
            $this->challenges->purge($challengeId);

            return response()->json(['error' => 'auth.2fa.challenge_expired'], 410);
        }

        if (! $this->twoFactor->verifyChallenge($user, $code)) {
            // S4: on the 5th miss the throttle will 429 the 6th attempt. Purge
            // the challenge in that moment so the user must re-login (defence
            // in depth). We check limiter state here to detect exhaustion.
            $key = 'throttle:2fa-challenge|'.$challengeId;

            if (RateLimiter::remaining('2fa-challenge', 5) <= 0) {
                $this->challenges->purge($challengeId);
            }

            return response()->json(['error' => 'auth.2fa.invalid_code'], 422);
        }

        Auth::login($user);
        $this->challenges->purge($challengeId);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $intendedUrl = $state['provider_context']['intended_url'] ?? null;

        return response()->json([
            'user' => new UserResource($user),
            'redirect_url' => is_string($intendedUrl) && $intendedUrl !== '' ? $intendedUrl : '/dashboard',
        ]);
    }

    public function disable(TwoFactorDisableRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return response()->json(['error' => 'auth.2fa.not_enabled'], 409);
        }

        $password = $request->input('password');
        $code = $request->input('code');

        if (is_string($password) && $password !== '' && ! empty($user->password)) {
            if (! Hash::check($password, (string) $user->password)) {
                return response()->json(['error' => 'auth.2fa.invalid_password'], 422);
            }
        } elseif (is_string($code) && $code !== '') {
            if (! $this->twoFactor->verifyChallenge($user, $code)) {
                return response()->json(['error' => 'auth.2fa.invalid_code'], 422);
            }
        } else {
            return response()->json(['error' => 'auth.2fa.invalid_password'], 422);
        }

        $this->twoFactor->disable($user);

        return response()->json(['success' => true]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return response()->json(['error' => 'auth.2fa.not_enabled'], 409);
        }

        return response()->json([
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user),
        ]);
    }
}
