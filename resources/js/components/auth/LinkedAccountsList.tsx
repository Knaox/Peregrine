import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { unlinkProvider, socialRedirectUrl } from '@/services/authApi';
import { useAuthProviders, useLinkedIdentities } from '@/hooks/useAuthProviders';
import { ApiError } from '@/services/http';
import type { AuthProviderId } from '@/types/AuthProvider';

/**
 * Profile > Security subsection showing currently-linked OAuth identities
 * plus buttons to link the remaining enabled providers.
 *
 * Unlink button is greyed out when the user's only remaining login method
 * would be this identity (plan §S7 — API returns can_unlink_any=false in
 * that case).
 */
export function LinkedAccountsList() {
    const { t } = useTranslation();
    const providers = useAuthProviders();
    const linked = useLinkedIdentities();
    const qc = useQueryClient();
    const [error, setError] = useState('');

    const unlink = useMutation({
        mutationFn: (provider: AuthProviderId) => unlinkProvider(provider),
        onSuccess: () => {
            setError('');
            void qc.invalidateQueries({ queryKey: ['linked-identities'] });
        },
        onError: (e) => {
            if (e instanceof ApiError && e.data['error'] === 'auth.social.cannot_unlink_last_method') {
                setError(t('auth.social.cannot_unlink_last_method'));
                return;
            }
            setError(t('common.error'));
        },
    });

    if (providers.isLoading || linked.isLoading) {
        return <p className="text-sm text-[var(--color-text-muted)]">{t('common.loading')}</p>;
    }

    const linkedProviderIds = new Set((linked.data?.data ?? []).map((i) => i.provider));
    const canUnlinkAny = linked.data?.can_unlink_any ?? false;
    const enabledProviders = providers.data?.providers ?? [];

    return (
        <div className="space-y-3">
            {error !== '' && (
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">
                    {error}
                </div>
            )}

            {enabledProviders.length === 0 && (
                <p className="text-sm text-[var(--color-text-muted)]">
                    {t('settings.security.no_providers_enabled')}
                </p>
            )}

            {enabledProviders.map((p) => {
                const isLinked = linkedProviderIds.has(p.id);
                const linkedIdentity = linked.data?.data.find((i) => i.provider === p.id);

                return (
                    <div
                        key={p.id}
                        className="flex items-center justify-between gap-3 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3"
                    >
                        <div>
                            <div className="text-sm font-medium text-[var(--color-text-primary)]">
                                {t(`auth.providers.${p.id}`)}
                            </div>
                            <div className="text-xs text-[var(--color-text-muted)]">
                                {isLinked && linkedIdentity !== undefined
                                    ? linkedIdentity.provider_email
                                    : t('settings.security.not_linked')}
                            </div>
                        </div>

                        {isLinked ? (
                            <button
                                type="button"
                                onClick={() => unlink.mutate(p.id)}
                                disabled={! canUnlinkAny || unlink.isPending}
                                className={clsx(
                                    'rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-1.5 text-xs font-medium',
                                    'text-[var(--color-text-secondary)] cursor-pointer',
                                    'hover:border-[var(--color-danger)]/50 hover:text-[var(--color-danger)]',
                                    'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:border-[var(--color-border)] disabled:hover:text-[var(--color-text-secondary)]',
                                )}
                                title={! canUnlinkAny ? t('auth.social.cannot_unlink_last_method') : ''}
                            >
                                {t('settings.security.unlink')}
                            </button>
                        ) : (
                            <a
                                href={socialRedirectUrl(p.id)}
                                className="rounded-[var(--radius)] bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white cursor-pointer hover:bg-[var(--color-primary-hover)]"
                            >
                                {t('settings.security.link')}
                            </a>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
