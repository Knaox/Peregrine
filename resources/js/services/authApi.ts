import { request } from '@/services/http';
import type {
    RecoveryCodesResponse,
    TwoFactorChallengeSuccess,
    TwoFactorSetupResponse,
} from '@/types/TwoFactor';

export async function twoFactorSetup(): Promise<TwoFactorSetupResponse> {
    return request('/api/auth/2fa/setup', { method: 'POST' });
}

export async function twoFactorConfirm(
    secret: string,
    code: string,
): Promise<RecoveryCodesResponse> {
    return request('/api/auth/2fa/confirm', {
        method: 'POST',
        body: JSON.stringify({ secret, code }),
    });
}

export async function twoFactorChallenge(
    challengeId: string,
    code: string,
): Promise<TwoFactorChallengeSuccess> {
    return request('/api/auth/2fa/challenge', {
        method: 'POST',
        body: JSON.stringify({ challenge_id: challengeId, code }),
    });
}

export async function twoFactorDisable(input: {
    password?: string;
    code?: string;
}): Promise<{ success: true }> {
    return request('/api/auth/2fa/disable', {
        method: 'POST',
        body: JSON.stringify(input),
    });
}

export async function twoFactorRegenerateRecoveryCodes(): Promise<RecoveryCodesResponse> {
    return request('/api/auth/2fa/recovery-codes/regenerate', { method: 'POST' });
}
