import { useTranslation } from 'react-i18next';
import type { ConnectionTestResult } from '../types';

interface ConnectionTestButtonProps {
    result: ConnectionTestResult;
    onTest: () => void;
    successMessage?: string;
    errorMessage?: string;
}

export function ConnectionTestButton({
    result,
    onTest,
    successMessage,
    errorMessage,
}: ConnectionTestButtonProps) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2">
            <button
                type="button"
                onClick={onTest}
                disabled={result.status === 'testing'}
                className="px-4 py-2 bg-[var(--color-surface-hover)] hover:bg-[var(--color-border)] text-[var(--color-text-primary)] rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)] disabled:opacity-50 disabled:cursor-not-allowed ring-1 ring-[var(--color-border)]"
            >
                {result.status === 'testing' ? (
                    <span className="flex items-center gap-2">
                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {t('common.loading')}
                    </span>
                ) : (
                    t('common.test_connection')
                )}
            </button>

            {result.status === 'success' && (
                <div className="flex items-center gap-2 text-[var(--color-success)] text-sm">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    {successMessage ?? t('common.connection_success')}
                </div>
            )}

            {result.status === 'error' && (
                <div className="flex items-center gap-2 text-[var(--color-danger)] text-sm">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    {errorMessage ?? result.error ?? t('common.connection_failed')}
                </div>
            )}
        </div>
    );
}
