import { useMutation } from '@tanstack/react-query';
import { setSftpPassword } from '@/services/userApi';

export function useSftpPassword() {
    const mutation = useMutation({
        mutationFn: setSftpPassword,
    });

    return {
        setSftpPassword: mutation.mutate,
        isPending: mutation.isPending,
        isSuccess: mutation.isSuccess,
        error: mutation.error,
        reset: mutation.reset,
    };
}
