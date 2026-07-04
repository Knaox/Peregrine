import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { VariableControlInput } from '@/components/server/VariableControlInput';
import { controlFor, describeRules, parseRules } from '@/services/variableRules';
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

/**
 * Controlled startup-variable card. Holds no value state of its own — the
 * parent (via {@link useStartupVariablesEditor}) owns it so every edit feeds a
 * single batch save through the global save bar. The control shape (toggle /
 * select / bounded number / text) is derived from the variable's Pelican
 * rules, and a localised hint line summarises the accepted format.
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
    const editable = canEdit && variable.is_editable;

    const parsed = useMemo(() => parseRules(variable.rules ?? ''), [variable.rules]);
    const control = useMemo(() => controlFor(parsed, value), [parsed, value]);
    const hints = useMemo(() => describeRules(parsed), [parsed]);

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

            <VariableControlInput
                control={control}
                value={value}
                editable={editable}
                isInvalid={isInvalid}
                ariaLabel={variable.name}
                emptyLabel={t('variables.empty_option')}
                onChange={(next) => onChange(variable.env_variable, next)}
            />

            {(hints.length > 0 || isInvalid) && (
                <p className={clsx('text-[11px] leading-relaxed', isInvalid ? 'text-[var(--color-danger)]' : 'text-[var(--color-text-muted)]')}>
                    {isInvalid && <span className="font-medium">{t('variables.invalid_value')} </span>}
                    {hints.map((hint) => t(`variables.${hint.token}`, hint.params)).join(' · ')}
                </p>
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
