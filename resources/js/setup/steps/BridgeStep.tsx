import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { FormField } from '../components/FormField';

export function BridgeStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();

    const toggleEnabled = () => {
        onChange({
            bridge: {
                ...data.bridge,
                enabled: !data.bridge.enabled,
            },
        });
    };

    const updateWebhookSecret = (value: string) => {
        onChange({
            bridge: { ...data.bridge, stripe_webhook_secret: value },
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <div className="flex items-center gap-3">
                    <h2 className="text-xl font-semibold text-white">
                        {t('setup.bridge.title')}
                    </h2>
                    <span className="px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-400 rounded-full">
                        {t('common.optional')}
                    </span>
                </div>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.bridge.description')}
                </p>
            </div>

            <div className="flex items-center justify-between p-4 bg-slate-800 border border-slate-700 rounded-xl">
                <span className="text-sm font-medium text-white">
                    {t('setup.bridge.enable')}
                </span>
                <button
                    type="button"
                    role="switch"
                    aria-checked={data.bridge.enabled}
                    onClick={toggleEnabled}
                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                        data.bridge.enabled ? 'bg-orange-500' : 'bg-slate-600'
                    }`}
                >
                    <span
                        className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                            data.bridge.enabled ? 'translate-x-6' : 'translate-x-1'
                        }`}
                    />
                </button>
            </div>

            {data.bridge.enabled ? (
                <div className="space-y-4">
                    <FormField
                        label={t('setup.bridge.stripe_webhook_secret')}
                        required
                        help={t('setup.bridge.stripe_webhook_secret_help', {
                            url: `${window.location.origin}/api/stripe/webhook`,
                        })}
                    >
                        <input
                            type="text"
                            value={data.bridge.stripe_webhook_secret}
                            onChange={(e) => updateWebhookSecret(e.target.value)}
                            placeholder={t('setup.bridge.stripe_webhook_secret_placeholder')}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                        />
                    </FormField>
                </div>
            ) : (
                <p className="text-sm text-slate-500">
                    {t('setup.bridge.disabled_note')}
                </p>
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
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
