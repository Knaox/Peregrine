import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { FormField } from '../components/FormField';
import { ConnectionTestButton } from '../components/ConnectionTestButton';
import { useConnectionTest } from '../hooks/useConnectionTest';
import { testPelican } from '../services/setupApi';

export function PelicanStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();

    const testFn = useCallback(
        () => testPelican(data.pelican),
        [data.pelican]
    );

    const { result, runTest, reset } = useConnectionTest(testFn);

    const updateField = (field: 'url' | 'api_key', value: string) => {
        reset();
        onChange({
            pelican: { ...data.pelican, [field]: value },
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-white">
                    {t('setup.pelican.title')}
                </h2>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.pelican.description')}
                </p>
            </div>

            <div className="space-y-4">
                <FormField label={t('setup.pelican.url')} required>
                    <input
                        type="url"
                        value={data.pelican.url}
                        onChange={(e) => updateField('url', e.target.value)}
                        placeholder={t('setup.pelican.url_placeholder')}
                        className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    />
                </FormField>

                <FormField
                    label={t('setup.pelican.api_key')}
                    required
                    help={t('setup.pelican.api_key_help')}
                >
                    <input
                        type="text"
                        value={data.pelican.api_key}
                        onChange={(e) => updateField('api_key', e.target.value)}
                        placeholder={t('setup.pelican.api_key_placeholder')}
                        className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                    />
                </FormField>
            </div>

            <div className="flex items-start gap-3 p-4 bg-slate-700/50 border border-slate-600 rounded-lg">
                <svg className="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p className="text-sm text-slate-400">
                    {t('setup.pelican.client_api_note')}
                </p>
            </div>

            <ConnectionTestButton
                result={result}
                onTest={runTest}
                successMessage={t('setup.pelican.test_success')}
                errorMessage={
                    result.error
                        ? t('setup.pelican.test_failed', { error: result.error })
                        : undefined
                }
            />

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
                    disabled={result.status !== 'success'}
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
