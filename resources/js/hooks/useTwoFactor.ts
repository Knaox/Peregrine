import { useMutation } from '@tanstack/react-query';
import {
    twoFactorConfirm,
    twoFactorDisable,
    twoFactorRegenerateRecoveryCodes,
    twoFactorSetup,
} from '@/services/authApi';

export function useTwoFactorSetup() {
    return useMutation({
        mutationFn: twoFactorSetup,
    });
}

export function useTwoFactorConfirm() {
    return useMutation({
        mutationFn: (input: { secret: string; code: string }) =>
            twoFactorConfirm(input.secret, input.code),
    });
}

export function useTwoFactorDisable() {
    return useMutation({
        mutationFn: (input: { password?: string; code?: string }) => twoFactorDisable(input),
    });
}

export function useTwoFactorRegenerateRecoveryCodes() {
    return useMutation({
        mutationFn: twoFactorRegenerateRecoveryCodes,
    });
}
