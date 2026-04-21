import { useQuery } from '@tanstack/react-query';
import { fetchAuthProviders, fetchLinkedIdentities } from '@/services/authApi';

/**
 * Drives the login page — which buttons to render, whether to show the
 * email/password form, whether the "Create account" link is visible. Cached
 * for 1min because the shape rarely changes and the admin can force a
 * refresh by reloading.
 */
export function useAuthProviders() {
    return useQuery({
        queryKey: ['auth-providers'],
        queryFn: fetchAuthProviders,
        staleTime: 60_000,
    });
}

/** User's currently linked identities — used in SecurityPage / profile. */
export function useLinkedIdentities() {
    return useQuery({
        queryKey: ['linked-identities'],
        queryFn: fetchLinkedIdentities,
        staleTime: 30_000,
    });
}
