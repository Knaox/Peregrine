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
                    <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                        {t('setup.bridge.title')}
                    </h2>
                    <span className="px-2 py-0.5 text-xs font-medium bg-[var(--color-surface-hover)] text-[var(--color-text-muted)] rounded-full">
                        {t('common.optional')}
                    </span>
                </div>
                <p className="text-[var(--color-text-secondary)] text-sm mt-1">
                    {t('setup.bridge.description')}
                </p>
            </div>

            <div className="flex items-center justify-between p-4 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-lg)]">
                <span className="text-sm font-medium text-[var(--color-text-primary)]">
                    {t('setup.bridge.enable')}
                </span>
                <button
                    type="button"
                    role="switch"
                    aria-checked={data.bridge.enabled}
                    onClick={toggleEnabled}
                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                        data.bridge.enabled ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-surface-hover)]'
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
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent font-mono"
                        />
                    </FormField>
                </div>
            ) : (
                <p className="text-sm text-[var(--color-text-muted)]">
                    {t('setup.bridge.disabled_note')}
                </p>
            )}

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    className="px-6 py-2 bg-[var(--color-surface-hover)] hover:bg-[var(--color-border)] text-[var(--color-text-primary)] rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)] ring-1 ring-[var(--color-border)]"
                >
                    {t('common.previous')}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    className="px-6 py-2 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)]"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
