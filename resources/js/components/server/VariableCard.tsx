import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import type { StartupVariable } from '@/types/StartupVariable';

const LockIcon = (
    <svg className="h-4 w-4 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <rect x="3" y="11" width="18" height="11" rx="2" />
        <path d="M7 11V7a5 5 0 0110 0v4" />
    </svg>
);

const InfoIcon = (
    <svg className="h-3.5 w-3.5 shrink-0 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <circle cx="12" cy="12" r="10" />
        <path d="M12 16v-4M12 8h.01" />
    </svg>
);

const LinkIcon = (
    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" />
    </svg>
);

function isBooleanRule(rules: string): boolean {
    return /\bboolean\b/.test(rules) || /\bin:[01],[01]\b/.test(rules);
}

/**
 * Controlled startup-variable card. Holds no value state of its own — the
 * parent (via {@link useStartupVariablesEditor}) owns it so every edit feeds a
 * single batch save through the global save bar. No per-card save button: a
 * "modified" badge + reset are the only per-card affordances.
 */
export function VariableCard({
    variable,
    value,
    onChange,
    onReset,
    isDirty,
    isInvalid,
    canEdit,
}: {
    variable: StartupVariable;
    value: string;
    onChange: (key: string, value: string) => void;
    onReset: (key: string) => void;
    isDirty: boolean;
    isInvalid: boolean;
    canEdit: boolean;
}) {
    const { t } = useTranslation('server-shell');
    const isBoolean = isBooleanRule(variable.rules);
    const editable = canEdit && variable.is_editable;

    const handleToggle = useCallback(() => {
        if (!editable) {
            return;
        }
        onChange(variable.env_variable, value === '1' ? '0' : '1');
    }, [editable, onChange, variable.env_variable, value]);

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.25, ease: 'easeOut' }}
            className={clsx(
                'rounded-[var(--radius)] border bg-[var(--color-surface)] p-4 space-y-3',
                isInvalid ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-[var(--color-text-primary)] truncate">
                        {variable.name}
                    </p>
                    <p className="font-mono text-xs text-[var(--color-text-muted)] truncate">
                        {variable.env_variable}
                    </p>
                </div>
                <div className="flex items-center gap-1.5 shrink-0">
                    {isDirty && editable && (
                        <button
                            type="button"
                            onClick={() => onReset(variable.env_variable)}
                            className="text-[10px] font-medium px-2 py-0.5 rounded text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors duration-[var(--transition-fast)]"
                            title={t('variables.reset')}
                        >
                            {t('variables.modified')}
                        </button>
                    )}
                    {variable.claimed && (
                        <span
                            className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded"
                            style={{ background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)' }}
                            title={t('variables.linked_hint')}
                        >
                            {LinkIcon}
                            {t('variables.linked')}
                        </span>
                    )}
                    {!variable.is_editable && (
                        <span title={t('variables.not_editable')}>{LockIcon}</span>
                    )}
                </div>
            </div>

            {isBoolean ? (
                <button
                    type="button"
                    role="switch"
                    aria-checked={value === '1'}
                    disabled={!editable}
                    onClick={handleToggle}
                    className={clsx(
                        'relative inline-flex h-6 w-11 shrink-0 rounded-full',
                        'transition-colors duration-[var(--transition-fast)]',
                        'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
                        value === '1' ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]',
                        !editable && 'opacity-50 cursor-not-allowed',
                    )}
                >
                    <span
                        className={clsx(
                            'pointer-events-none inline-block h-5 w-5 rounded-full',
                            'bg-white shadow transform transition-transform duration-[var(--transition-fast)]',
                            'mt-0.5',
                            value === '1' ? 'translate-x-[22px]' : 'translate-x-0.5',
                        )}
                    />
                </button>
            ) : (
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(variable.env_variable, e.target.value)}
                    readOnly={!editable}
                    disabled={!variable.is_editable}
                    className={clsx(
                        'w-full px-3 py-2 text-sm',
                        'bg-[var(--color-surface)] rounded-[var(--radius)]',
                        'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                        'border transition-all duration-[var(--transition-fast)]',
                        'focus:outline-none focus:ring-2 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
                        isInvalid ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]',
                        !editable && 'opacity-50 cursor-not-allowed',
                    )}
                />
            )}

            {variable.description && (
                <div className="flex items-start gap-1.5">
                    {InfoIcon}
                    <p className="text-xs text-[var(--color-text-muted)] leading-relaxed">
                        {variable.description}
                    </p>
                </div>
            )}
        </m.div>
    );
}
