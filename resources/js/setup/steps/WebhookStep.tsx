import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { generateWebhookToken, getWebhookHeartbeat, finalizeSetup } from '../services/setupApi';

export function WebhookStep(_: StepProps) {
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

    const selectAll = (e: React.MouseEvent<HTMLInputElement>) =>
        (e.target as HTMLInputElement).select();

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
                <div className="space-y-5">
                    <div className="rounded-lg border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-4 text-sm">
                        <p className="font-medium text-[var(--color-text-primary)]">
                            {t('setup.webhook.howto_intro', { defaultValue: 'Dans Pelican, va dans /admin/webhooks → Create Webhook et remplis exactement comme ci-dessous.' })}
                        </p>
                    </div>

                    {/* === Pelican form: Type === */}
                    <div>
                        <label className="text-xs font-medium uppercase text-[var(--color-text-secondary)]">
                            {t('setup.webhook.type_label', { defaultValue: 'Type (champ Pelican)' })}
                        </label>
                        <input
                            type="text"
                            readOnly
                            value="Regular"
                            onClick={selectAll}
                            className="mt-1 w-full rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                        />
                    </div>

                    {/* === Pelican form: Endpoint === */}
                    <div>
                        <label className="text-xs font-medium uppercase text-[var(--color-text-secondary)]">
                            {t('setup.webhook.endpoint_label', { defaultValue: 'Endpoint (champ Pelican)' })}
                        </label>
                        <input
                            type="text"
                            readOnly
                            value={endpoint}
                            onClick={selectAll}
                            className="mt-1 w-full rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                        />
                    </div>

                    {/* === Pelican form: Headers (2 rows) === */}
                    <div>
                        <label className="text-xs font-medium uppercase text-[var(--color-text-secondary)]">
                            {t('setup.webhook.headers_label', { defaultValue: 'Headers (section Pelican — clique sur "Add row" pour ajouter Authorization)' })}
                        </label>
                        <div className="mt-1 overflow-hidden rounded-lg border border-[var(--color-glass-border)]">
                            <div className="grid grid-cols-2 gap-px bg-[var(--color-glass-border)] text-xs uppercase tracking-wide">
                                <div className="bg-[var(--color-surface)] px-3 py-2 text-[var(--color-text-secondary)]">Key</div>
                                <div className="bg-[var(--color-surface)] px-3 py-2 text-[var(--color-text-secondary)]">Value</div>
                            </div>
                            {/* Row 1 — Pelican's default, do NOT remove */}
                            <div className="grid grid-cols-2 gap-px border-t border-[var(--color-glass-border)] bg-[var(--color-glass-border)]">
                                <input
                                    type="text"
                                    readOnly
                                    value="X-Webhook-Event"
                                    onClick={selectAll}
                                    className="bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                                />
                                <input
                                    type="text"
                                    readOnly
                                    value="{{event}}"
                                    onClick={selectAll}
                                    className="bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                                />
                            </div>
                            {/* Row 2 — Authorization Bearer */}
                            <div className="grid grid-cols-2 gap-px border-t border-[var(--color-glass-border)] bg-[var(--color-glass-border)]">
                                <input
                                    type="text"
                                    readOnly
                                    value="Authorization"
                                    onClick={selectAll}
                                    className="bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                                />
                                <input
                                    type="text"
                                    readOnly
                                    value={`Bearer ${token}`}
                                    onClick={selectAll}
                                    className="bg-[var(--color-glass)] px-3 py-2 font-mono text-sm"
                                />
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-[var(--color-text-secondary)]">
                            {t('setup.webhook.headers_hint', { defaultValue: 'La 1ère ligne (X-Webhook-Event = {{event}}) est le défaut Pelican — laisse-la. La 2ème ligne (Authorization) est à AJOUTER manuellement via "Add row".' })}
                        </p>
                        <p className="mt-1 text-xs text-amber-500">
                            {t('setup.webhook.copy_warning', { defaultValue: 'Copie le token maintenant — il ne sera plus affiché en clair après cette étape.' })}
                        </p>
                    </div>

                    {/* === Events to tick === */}
                    <div className="rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-4 text-sm">
                        <p className="font-medium">{t('setup.webhook.events_to_tick', { defaultValue: 'Events à cocher (recherche-les dans la liste Pelican)' })}</p>
                        <ul className="mt-2 list-inside list-disc space-y-1 text-[var(--color-text-secondary)]">
                            <li><code>created: Server</code> · <code>updated: Server</code> · <code>deleted: Server</code></li>
                            <li><code>event: Server\Installed</code> <span className="text-xs">— {t('setup.webhook.installed_note', { defaultValue: 'optionnel, sur Pelican 0.46+ stable' })}</span></li>
                            <li><code>created: User</code> · <code>updated: User</code> · <code>deleted: User</code></li>
                            <li><code>created/updated/deleted: Node</code></li>
                            <li><code>created/updated/deleted: Egg</code> · <code>EggVariable</code></li>
                            <li><code>created/updated/deleted: Backup</code></li>
                            <li><code>created/updated/deleted: Allocation</code></li>
                            <li><code>created/updated/deleted: Database</code> · <code>DatabaseHost</code></li>
                            <li><code>created/updated/deleted: ServerTransfer</code></li>
                            <li><code>event: Server\SubUserAdded</code> · <code>event: Server\SubUserRemoved</code></li>
                        </ul>
                        <p className="mt-3 text-xs text-rose-500">
                            {t('setup.webhook.do_not_tick', { defaultValue: 'NE PAS cocher (flood / boucles) : event: ActivityLogged, Schedule, Task, ApiKey, Webhook, WebhookConfiguration.' })}
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

            <div className="flex justify-end pt-4">
                {/* Last step of the wizard — no Précédent (going back would
                    rerun the install on Summary). Finish redirects to /. */}
                <button
                    type="button"
                    onClick={async () => {
                        // Tell the backend the wizard is done so EnsureInstalled
                        // stops keeping /setup reachable, THEN navigate to /. We
                        // await the call but don't block on errors — the sentinel
                        // also auto-expires after 1h.
                        await finalizeSetup();
                        window.location.href = '/';
                    }}
                    className="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white"
                >
                    {token
                        ? t('setup.webhook.finish', { defaultValue: 'Terminer' })
                        : t('setup.webhook.skip', { defaultValue: 'Passer et terminer (sync quotidienne)' })}
                </button>
            </div>
        </div>
    );
}
