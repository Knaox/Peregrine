import { useTranslation } from 'react-i18next';

export function ServerEmptyState() {
    const { t } = useTranslation();

    return (
        <div className="rounded-xl border border-slate-700 bg-slate-800 p-12 text-center">
            <svg
                className="mx-auto mb-4 h-12 w-12 text-slate-500"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={1.5}
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"
                />
            </svg>
            <p className="text-slate-400">{t('servers.list.empty')}</p>
        </div>
    );
}
