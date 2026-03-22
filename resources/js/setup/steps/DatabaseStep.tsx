import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DatabaseConfig, StepProps } from '../types';
import { FormField } from '../components/FormField';
import { ConnectionTestButton } from '../components/ConnectionTestButton';
import { useConnectionTest } from '../hooks/useConnectionTest';
import { testDatabase, detectDocker } from '../services/setupApi';

export function DatabaseStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();
    const [dockerDetected, setDockerDetected] = useState(false);
    const [dbReady, setDbReady] = useState(false);

    const testFn = useCallback(
        () => testDatabase(data.database),
        [data.database]
    );

    const { result, runTest, reset } = useConnectionTest(testFn);

    useEffect(() => {
        detectDocker()
            .then((res) => {
                if (res.is_docker) {
                    setDockerDetected(true);
                    onChange({
                        database: {
                            ...data.database,
                            host: (res.defaults.host as string) ?? data.database.host,
                            port: (res.defaults.port as number) ?? data.database.port,
                            database: (res.defaults.database as string) ?? data.database.database,
                            username: (res.defaults.username as string) ?? data.database.username,
                        },
                    });
                    if (res.db_ready) {
                        setDbReady(true);
                    }
                }
            })
            .catch(() => {
                // Silently ignore docker detection failures
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only on mount
    }, []);

    const updateField = (field: keyof DatabaseConfig, value: string | number) => {
        reset();
        setDbReady(false);
        onChange({
            database: { ...data.database, [field]: value },
        });
    };

    const canProceed = result.status === 'success' || dbReady;

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-white">
                    {t('setup.database.title')}
                </h2>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.database.description')}
                </p>
            </div>

            {dockerDetected && dbReady && (
                <div className="flex items-start gap-3 p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                    <svg className="w-5 h-5 text-green-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <p className="text-sm text-green-300">
                        {t('setup.database.docker_db_ready')}
                    </p>
                </div>
            )}

            {dockerDetected && !dbReady && (
                <div className="flex items-start gap-3 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                    <svg className="w-5 h-5 text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-sm text-blue-300">
                        {t('setup.database.docker_detected')}
                    </p>
                </div>
            )}

            <div className="space-y-4">
                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2">
                        <FormField label={t('setup.database.host')} required>
                            <input
                                type="text"
                                value={data.database.host}
                                onChange={(e) => updateField('host', e.target.value)}
                                placeholder={t('setup.database.host_placeholder')}
                                className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                            />
                        </FormField>
                    </div>
                    <FormField label={t('setup.database.port')} required>
                        <input
                            type="number"
                            value={data.database.port}
                            onChange={(e) => updateField('port', parseInt(e.target.value, 10) || 0)}
                            placeholder={t('setup.database.port_placeholder')}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>
                </div>

                <FormField label={t('setup.database.name')} required>
                    <input
                        type="text"
                        value={data.database.database}
                        onChange={(e) => updateField('database', e.target.value)}
                        placeholder={t('setup.database.name_placeholder')}
                        className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    />
                </FormField>

                <FormField label={t('setup.database.username')} required>
                    <input
                        type="text"
                        value={data.database.username}
                        onChange={(e) => updateField('username', e.target.value)}
                        placeholder={t('setup.database.username_placeholder')}
                        className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    />
                </FormField>

                <FormField label={t('setup.database.password')}>
                    <input
                        type="password"
                        value={data.database.password}
                        onChange={(e) => updateField('password', e.target.value)}
                        placeholder={t('setup.database.password_placeholder')}
                        className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    />
                </FormField>
            </div>

            {!dbReady && (
                <ConnectionTestButton
                    result={result}
                    onTest={runTest}
                    successMessage={t('setup.database.test_success')}
                    errorMessage={
                        result.error
                            ? t('setup.database.test_failed', { error: result.error })
                            : undefined
                    }
                />
            )}

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    className="px-6 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors"
                >
                    {t('common.previous')}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    disabled={!canProceed}
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
