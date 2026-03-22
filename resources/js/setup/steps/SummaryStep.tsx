import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { install } from '../services/setupApi';

type InstallStatus = 'idle' | 'installing' | 'success' | 'error';

export function SummaryStep({ data, onPrevious }: StepProps) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<InstallStatus>('idle');
    const [error, setError] = useState<string>('');

    const handleInstall = async () => {
        setStatus('installing');
        setError('');

        try {
            const response = await install(data);
            if (response.success) {
                setStatus('success');
                setTimeout(() => {
                    window.location.href = '/';
                }, 2000);
            } else {
                setStatus('error');
                setError(response.error ?? t('common.error'));
            }
        } catch (err: unknown) {
            setStatus('error');
            const message = err instanceof Error ? err.message : t('common.error');
            setError(message);
        }
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-white">
                    {t('setup.summary.title')}
                </h2>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.summary.description')}
                </p>
            </div>

            <div className="space-y-4">
                {/* Database */}
                <div className="p-4 bg-slate-800 border border-slate-700 rounded-xl">
                    <h3 className="text-sm font-semibold text-slate-300 mb-2">
                        {t('setup.summary.section_database')}
                    </h3>
                    <p className="text-sm text-white font-mono">
                        {data.database.host}:{data.database.port}/{data.database.database}
                    </p>
                    <p className="text-xs text-slate-500 mt-1">
                        {data.database.username}
                    </p>
                </div>

                {/* Admin */}
                <div className="p-4 bg-slate-800 border border-slate-700 rounded-xl">
                    <h3 className="text-sm font-semibold text-slate-300 mb-2">
                        {t('setup.summary.section_admin')}
                    </h3>
                    <p className="text-sm text-white">{data.admin.name}</p>
                    <p className="text-xs text-slate-500 mt-1">{data.admin.email}</p>
                </div>

                {/* Pelican */}
                <div className="p-4 bg-slate-800 border border-slate-700 rounded-xl">
                    <h3 className="text-sm font-semibold text-slate-300 mb-2">
                        {t('setup.summary.section_pelican')}
                    </h3>
                    <p className="text-sm text-white font-mono">{data.pelican.url}</p>
                </div>

                {/* Auth */}
                <div className="p-4 bg-slate-800 border border-slate-700 rounded-xl">
                    <h3 className="text-sm font-semibold text-slate-300 mb-2">
                        {t('setup.summary.section_auth')}
                    </h3>
                    <p className="text-sm text-white">
                        {data.auth.mode === 'local'
                            ? t('setup.auth.mode_local')
                            : t('setup.auth.mode_oauth')}
                    </p>
                    {data.auth.mode === 'oauth' && (
                        <div className="mt-2 space-y-1">
                            <p className="text-xs text-slate-500">
                                {t('setup.auth.oauth_authorize_url')}: {data.auth.oauth_authorize_url}
                            </p>
                            <p className="text-xs text-slate-500">
                                {t('setup.auth.oauth_token_url')}: {data.auth.oauth_token_url}
                            </p>
                            <p className="text-xs text-slate-500">
                                {t('setup.auth.oauth_user_url')}: {data.auth.oauth_user_url}
                            </p>
                        </div>
                    )}
                </div>

                {/* Bridge */}
                <div className="p-4 bg-slate-800 border border-slate-700 rounded-xl">
                    <h3 className="text-sm font-semibold text-slate-300 mb-2">
                        {t('setup.summary.section_bridge')}
                    </h3>
                    <p className="text-sm text-white">
                        {data.bridge.enabled ? (
                            <span className="inline-flex items-center gap-1.5">
                                <span className="w-2 h-2 rounded-full bg-green-500" />
                                {t('setup.bridge.enable')}
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5">
                                <span className="w-2 h-2 rounded-full bg-slate-500" />
                                {t('setup.bridge.disabled_note')}
                            </span>
                        )}
                    </p>
                </div>
            </div>

            {/* Install status messages */}
            {status === 'installing' && (
                <div className="flex items-center gap-3 p-4 bg-orange-500/10 border border-orange-500/30 rounded-lg">
                    <svg className="animate-spin h-5 w-5 text-orange-500" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span className="text-sm text-orange-300">
                        {t('common.installing')}
                    </span>
                </div>
            )}

            {status === 'success' && (
                <div className="flex items-center gap-3 p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                    <svg className="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-sm text-green-300">
                        {t('setup.summary.install_complete')}
                    </span>
                </div>
            )}

            {status === 'error' && (
                <div className="flex items-center gap-3 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                    <svg className="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span className="text-sm text-red-300">{error}</span>
                </div>
            )}

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    disabled={status === 'installing' || status === 'success'}
                    className="px-6 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {t('common.previous')}
                </button>
                <button
                    type="button"
                    onClick={handleInstall}
                    disabled={status === 'installing' || status === 'success'}
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {status === 'installing' ? (
                        <span className="flex items-center gap-2">
                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {t('common.installing')}
                        </span>
                    ) : (
                        t('setup.summary.install_button')
                    )}
                </button>
            </div>
        </div>
    );
}
