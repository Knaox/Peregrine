import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import type { OperationCompletionAlertProps } from '@/components/server/overview/OperationCompletionAlert.props';

/**
 * One-shot success banner shown when a long-running operation (install,
 * unsuspend, modpack…) just completed. Extracted from ServerOverviewPage
 * to keep the page within the 300-line rule.
 */
export function OperationCompletionAlert({ message, onDismiss }: OperationCompletionAlertProps) {
    const { t } = useTranslation();

    return (
        <Alert variant='success'>
            <div className='flex w-full items-center justify-between gap-3'>
                <span>{message}</span>
                <button
                    type='button'
                    onClick={onDismiss}
                    className='text-xs opacity-70 transition-opacity hover:opacity-100'
                    aria-label={t('server-overview:operations.completion.dismiss')}
                >
                    {t('server-overview:operations.completion.dismiss')}
                </button>
            </div>
        </Alert>
    );
}
