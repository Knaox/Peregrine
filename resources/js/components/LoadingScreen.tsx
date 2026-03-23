import { useTranslation } from 'react-i18next';

export function LoadingScreen() {
    const { t } = useTranslation();

    return (
        <div className="min-h-screen bg-[var(--color-background)] flex items-center justify-center">
            <div className="flex flex-col items-center gap-4">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-[var(--color-border)] border-t-[var(--color-primary)]" />
                <p className="text-[var(--color-text-secondary)] text-sm">{t('common.loading')}</p>
            </div>
        </div>
    );
}
