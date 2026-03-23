import { useTranslation } from 'react-i18next';

export function ComingSoonPage() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-[40vh] flex-col items-center justify-center gap-3">
            <svg className="h-12 w-12 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17l-5.58-3.22a.5.5 0 010-.88l5.58-3.22a.5.5 0 01.58 0l5.58 3.22a.5.5 0 010 .88l-5.58 3.22a.5.5 0 01-.58 0zM12 2v2m0 16v2m10-10h-2M4 12H2m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414m12.728 0l-1.414-1.414M7.05 7.05L5.636 5.636" />
            </svg>
            <p className="text-lg font-medium text-[var(--color-text-secondary)]">{t('common.coming_soon')}</p>
        </div>
    );
}
