import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { generateWebhookToken, getWebhookHeartbeat } from '../services/setupApi';

export function WebhookStep({ onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();
    const [token, setToken] = useState<string | null>(null);
    const [endpoint, setEndpoint] = useState<string>('');
    const [verifying, setVerifying] = useState(false);
    const [verified, setVerified] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const generate = async () => {
        setError(null);
        try {
            const result = await generateWebhookToken();
            setToken(result.token);
            setEndpoint(result.endpoint);
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    const verify = async () => {
        setVerifying(true);
        setError(null);
        try {
            const status = await getWebhookHeartbeat();
            if (status.enabled && status.token_configured) {
                setVerified(true);
            } else {
                setError(t('setup.webhook.verify_failed', { defaultValue: 'Le récepteur ne répond pas correctement. Réessaie ou continue.' }));
            }
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setVerifying(false);
        }
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.webhook.title', { defaultValue: 'Activer la synchronisation temps réel' })}
                </h2>
                <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                    {t('setup.webhook.subtitle', { defaultValue: 'Configure Pelican pour notifier Peregrine en direct (recommandé). Tu peux passer cette étape — Peregrine se synchronisera quand même tous les jours.' })}
                </p>
            </div>

            {! token && (
                <button
                    type="button"
                    onClick={generate}
                    className="w-full rounded-lg bg-[var(--color-primary)] px-5 py-3 text-sm font-medium text-white"
                >
                    {t('setup.webhook.generate', { defaultValue: 'Générer le token webhook' })}
                </button>
            )}

            {token && (
                <div className="space-y-3">
                    <div>
                        <label className="text-xs font-medium uppercase text-[var(--color-text-secondary)]">
                            {t('setup.webhook.endpoint_label', { defaultValue: 'Endpoint à coller dans Pelican /admin/webhooks' })}
                        </label>
                        <input
                            type="text"
                            readOnly
                            value={endpoint}
                            onClick={(e) => (e.target as HTMLInputElement).select()}
                            className="mt-1 w-full rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium uppercase text-[var(--color-text-secondary)]">
                            {t('setup.webhook.token_label', { defaultValue: 'Token (header Authorization: Bearer ...)' })}
                        </label>
                        <input
                            type="text"
                            readOnly
                            value={token}
                            onClick={(e) => (e.target as HTMLInputElement).select()}
                            className="mt-1 w-full rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                        />
                        <p className="mt-1 text-xs text-amber-500">
                            {t('setup.webhook.copy_warning', { defaultValue: 'Copie maintenant — il ne sera plus affiché.' })}
                        </p>
                    </div>
                    <div className="rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-4 text-sm">
                        <p className="font-medium">{t('setup.webhook.events_to_tick', { defaultValue: 'Events à cocher dans Pelican' })}</p>
                        <ul className="mt-2 list-inside list-disc space-y-1 text-[var(--color-text-secondary)]">
                            <li><code>created: Server</code></li>
                            <li><code>updated: Server</code></li>
                            <li><code>deleted: Server</code></li>
                            <li><code>created: User</code> + <code>updated: User</code> + <code>deleted: User</code></li>
                            <li><code>created/updated/deleted: Node</code></li>
                            <li><code>created/updated/deleted: Egg</code> + <code>EggVariable</code></li>
                            <li><code>created/updated/deleted: Backup</code></li>
                            <li><code>created/updated/deleted: Allocation</code></li>
                            <li><code>created/updated/deleted: Database</code> + <code>DatabaseHost</code></li>
                            <li><code>created/updated/deleted: ServerTransfer</code></li>
                        </ul>
                        <p className="mt-2 text-xs text-rose-500">
                            {t('setup.webhook.do_not_tick', { defaultValue: 'NE PAS cocher : event: Server\\Installed (bug Pelican), ActivityLogged, Schedule, ApiKey, Webhook (loop).' })}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={verify}
                        disabled={verifying}
                        className="w-full rounded-lg border border-[var(--color-glass-border)] px-5 py-2 text-sm hover:bg-[var(--color-glass)] disabled:opacity-50"
                    >
                        {verifying
                            ? t('setup.webhook.verifying', { defaultValue: 'Vérification…' })
                            : t('setup.webhook.verify', { defaultValue: 'Vérifier la configuration' })}
                    </button>
                    {verified && (
                        <p className="text-sm text-emerald-500">✓ {t('setup.webhook.verified', { defaultValue: 'Récepteur prêt.' })}</p>
                    )}
                </div>
            )}

            {error && (
                <div className="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-500">
                    {error}
                </div>
            )}

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    className="rounded-lg border border-[var(--color-glass-border)] px-5 py-2 text-sm hover:bg-[var(--color-glass)]"
                >
                    {t('common.previous', { defaultValue: 'Précédent' })}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    className="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white"
                >
                    {token
                        ? t('common.next', { defaultValue: 'Suivant' })
                        : t('setup.webhook.skip', { defaultValue: 'Passer (sync quotidienne)' })}
                </button>
            </div>
        </div>
    );
}
