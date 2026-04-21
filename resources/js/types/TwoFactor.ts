export interface TwoFactorSetupResponse {
    secret: string;
    qr_svg_base64: string;
    otpauth_uri: string;
}

export interface RecoveryCodesResponse {
    recovery_codes: string[];
}

export interface TwoFactorChallengeSuccess {
    user: import('@/types/User').User;
    redirect_url: string;
}

/**
 * Login response shape — may resolve immediately with a user OR deflect to
 * the 2FA challenge page with an opaque challenge_id.
 */
export type LoginResponse =
    | { user: import('@/types/User').User; requires_2fa?: undefined; challenge_id?: undefined }
    | { requires_2fa: true; challenge_id: string; user?: undefined };
