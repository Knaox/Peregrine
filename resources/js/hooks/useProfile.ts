import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchProfile, updateProfile, changePassword } from '@/services/userApi';

export function useProfile() {
    const queryClient = useQueryClient();

    const { data: profile, isLoading } = useQuery({
        queryKey: ['user', 'profile'],
        queryFn: fetchProfile,
        staleTime: 60_000,
    });

    const updateMutation = useMutation({
        mutationFn: updateProfile,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user', 'profile'] });
        },
    });

    const passwordMutation = useMutation({
        mutationFn: changePassword,
    });

    return {
        profile,
        isLoading,
        updateProfile: updateMutation.mutate,
        isUpdating: updateMutation.isPending,
        isUpdateSuccess: updateMutation.isSuccess,
        changePassword: passwordMutation.mutate,
        isChangingPassword: passwordMutation.isPending,
        isPasswordChanged: passwordMutation.isSuccess,
        passwordError: passwordMutation.error,
    };
}
