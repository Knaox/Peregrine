import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';

interface RecoveryCodesDisplayProps {
    codes: string[];
    onAcknowledge: () => void;
}

export function RecoveryCodesDisplay({ codes, onAcknowledge }: RecoveryCodesDisplayProps) {
    const { t } = useTranslation();
    const [saved, setSaved] = useState(false);

    const download = (): void => {
        const blob = new Blob([codes.join('\n')], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `peregrine-recovery-codes-${new Date().toISOString().slice(0, 10)}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-[var(--color-text-primary)]">
                {t('auth.2fa.setup.recovery_title')}
            </h3>
            <p className="text-sm text-[var(--color-text-muted)]">{t('auth.2fa.setup.recovery_intro')}</p>

            <div className="grid grid-cols-2 gap-2 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 font-mono text-sm text-[var(--color-text-primary)]">
                {codes.map((code) => (
                    <div key={code} className="tracking-wider">
                        {code}
                    </div>
                ))}
            </div>

            <button
                type="button"
                onClick={download}
                className="w-full rounded-[var(--radius)] border border-[var(--color-border)] px-4 py-2.5 text-sm font-medium text-[var(--color-text-secondary)] hover:border-[var(--color-border-hover)] cursor-pointer transition-colors"
            >
                {t('auth.2fa.setup.recovery_download')}
            </button>

            <label className="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    checked={saved}
                    onChange={(e) => setSaved(e.target.checked)}
                    className="h-4 w-4 rounded border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] cursor-pointer"
                />
                <span className="text-sm text-[var(--color-text-secondary)]">
                    {t('auth.2fa.setup.recovery_acknowledge')}
                </span>
            </label>

            <button
                type="button"
                disabled={!saved}
                onClick={onAcknowledge}
                className={clsx(
                    'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white cursor-pointer',
                    'shadow-[0_4px_20px_var(--color-primary-glow)] transition-all duration-200',
                    'hover:bg-[var(--color-primary-hover)]',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                )}
            >
                {t('auth.2fa.setup.recovery_done_cta')}
            </button>
        </div>
    );
}
