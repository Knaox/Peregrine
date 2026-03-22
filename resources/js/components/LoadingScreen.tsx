import { useTranslation } from 'react-i18next';

export function LoadingScreen() {
    const { t } = useTranslation();

    return (
        <div className="min-h-screen bg-slate-900 flex items-center justify-center">
            <div className="flex flex-col items-center gap-4">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-orange-500" />
                <p className="text-slate-400 text-sm">{t('common.loading')}</p>
            </div>
        </div>
    );
}
