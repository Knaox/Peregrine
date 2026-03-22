import { useState, useCallback } from 'react';
import type { ConnectionTestResult } from '../types';

export function useConnectionTest(
    testFn: () => Promise<{ success: boolean; error?: string }>
) {
    const [result, setResult] = useState<ConnectionTestResult>({ status: 'idle' });

    const runTest = useCallback(async () => {
        setResult({ status: 'testing' });
        try {
            const response = await testFn();
            if (response.success) {
                setResult({ status: 'success' });
            } else {
                setResult({ status: 'error', error: response.error });
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setResult({ status: 'error', error: message });
        }
    }, [testFn]);

    const reset = useCallback(() => {
        setResult({ status: 'idle' });
    }, []);

    return { result, runTest, reset };
}
