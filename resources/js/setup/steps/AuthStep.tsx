import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';

/**
 * Simplified auth step — a single toggle for local registration.
 *
 * OAuth providers (Shop, Google, Discord, LinkedIn) and 2FA are configured
 * post-install from the admin panel (Settings → Auth & Security), not here.
 * Keeping install-time config minimal avoids guiding new installs into
 * filling provider credentials they don't have yet.
 */
export function AuthStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();

    const toggle = (value: boolean): void => {
        onChange({
            auth: { ...data.auth, allow_local_registration: value },
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.auth.title')}
                </h2>
                <p className="text-[var(--color-text-secondary)] text-sm mt-1">
                    {t('setup.auth.description')}
                </p>
            </div>

            <label
                className={`flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                    data.auth.allow_local_registration
                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                        : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-hover)]'
                }`}
            >
                <input
                    type="checkbox"
                    checked={data.auth.allow_local_registration}
                    onChange={(e) => toggle(e.target.checked)}
                    className="mt-1 h-4 w-4 rounded text-[var(--color-primary)] focus:ring-[var(--color-primary)] bg-[var(--color-surface-hover)] border-[var(--color-border)] cursor-pointer"
                />
                <div>
                    <span className="block text-sm font-medium text-[var(--color-text-primary)]">
                        {t('setup.auth.allow_local_registration.label')}
                    </span>
                    <span className="block text-xs text-[var(--color-text-secondary)] mt-1">
                        {t('setup.auth.allow_local_registration.help')}
                    </span>
                </div>
            </label>

            <div
                className="flex items-start gap-3 rounded-[var(--radius)] border p-4 text-sm"
                style={{
                    background: 'rgba(var(--color-primary-rgb), 0.06)',
                    borderColor: 'rgba(var(--color-primary-rgb), 0.25)',
                    color: 'var(--color-text-secondary)',
                }}
            >
                <svg
                    className="mt-0.5 h-5 w-5 shrink-0 text-[var(--color-primary)]"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <circle cx="12" cy="12" r="10" strokeLinecap="round" strokeLinejoin="round" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 16v-4M12 8h.01" />
                </svg>
                <span>{t('setup.auth.post_install_note')}</span>
            </div>

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
