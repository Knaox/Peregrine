import { useQuery, useMutation } from '@tanstack/react-query';
import { request } from '@/services/http';

interface InvitationPublic {
    email: string;
    server_name: string;
    /** All servers this invite grants access to (1 for a normal invite, N for a multi-server batch). */
    servers?: string[];
    server_count?: number;
    inviter_name: string;
    permissions: Array<{ key: string; label: string }>;
    expires_at: string;
    is_active: boolean;
    is_accepted: boolean;
    is_revoked: boolean;
    /** True when the invited email already has a Peregrine account → show login, not register. */
    user_exists: boolean;
}

interface AcceptResponse {
    message: string;
    /** First server accepted — used for the post-accept redirect. */
    server_id: number;
    /** Every server accepted in a multi-server batch. */
    server_ids?: number[];
}

interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export function useInvitationPublic(token: string) {
    return useQuery({
        queryKey: ['invitation-public', token],
        queryFn: () => request<InvitationPublic>(
            `/api/plugins/invitations/invite/${token}`,
        ),
        retry: false,
        staleTime: 60_000,
    });
}

export function useAcceptInvitation() {
    return useMutation({
        mutationFn: (token: string) =>
            request<AcceptResponse>(
                `/api/plugins/invitations/invite/${token}/accept`,
                { method: 'POST' },
            ),
    });
}

export function useRegisterAndAccept() {
    return useMutation({
        mutationFn: ({ token, data }: { token: string; data: RegisterData }) =>
            request<{ message: string }>(
                `/api/plugins/invitations/invite/${token}/register`,
                { method: 'POST', body: JSON.stringify(data) },
            ),
    });
}

export type { InvitationPublic, RegisterData };
