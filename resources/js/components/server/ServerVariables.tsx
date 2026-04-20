import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { GlassCard } from '@/components/ui/GlassCard';
import { Spinner } from '@/components/ui/Spinner';
import { useStartupVariables } from '@/hooks/useStartupVariables';
import type { ServerVariablesProps } from '@/components/server/ServerVariables.props';
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

function isBooleanRule(rules: string): boolean {
    return /\bboolean\b/.test(rules) || /\bin:[01],[01]\b/.test(rules);
}

function VariableCard({
    variable,
    onSave,
    isSaving,
    canEdit,
}: {
    variable: StartupVariable;
    onSave: (key: string, value: string) => void;
    isSaving: boolean;
    canEdit: boolean;
}) {
    const { t } = useTranslation();
    const currentValue = variable.server_value ?? variable.default_value ?? '';
    const [value, setValue] = useState(currentValue);
    const isBoolean = isBooleanRule(variable.rules);
    const hasChanged = value !== currentValue;
    const editable = canEdit && variable.is_editable;

    const handleToggle = useCallback(() => {
        if (!editable) return;
        const next = value === '1' ? '0' : '1';
        setValue(next);
    }, [value, editable]);

    const handleSave = useCallback(() => {
        onSave(variable.env_variable, value);
    }, [onSave, variable.env_variable, value]);

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.25, ease: 'easeOut' }}
            className={clsx(
                'rounded-[var(--radius)] border border-[var(--color-border)]',
                'bg-[var(--color-surface)] p-4 space-y-3',
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
                {!variable.is_editable && (
                    <span title={t('servers.variables.not_editable')}>{LockIcon}</span>
                )}
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
                        value === '1'
                            ? 'bg-[var(--color-primary)]'
                            : 'bg-[var(--color-border)]',
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
                    onChange={(e) => setValue(e.target.value)}
                    readOnly={!editable}
                    disabled={!variable.is_editable}
                    className={clsx(
                        'w-full px-3 py-2 text-sm',
                        'bg-[var(--color-surface)] rounded-[var(--radius)]',
                        'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                        'border border-[var(--color-border)]',
                        'transition-all duration-[var(--transition-fast)]',
                        'focus:outline-none focus:ring-2 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
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

            {hasChanged && editable && (
                <m.button
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    type="button"
                    disabled={isSaving}
                    onClick={handleSave}
                    className={clsx(
                        'px-3 py-1.5 text-xs font-medium rounded-[var(--radius)]',
                        'bg-[var(--color-primary)] text-white',
                        'transition-all duration-[var(--transition-fast)]',
                        'hover:opacity-90 disabled:opacity-50',
                    )}
                >
                    {isSaving ? <Spinner size="sm" /> : t('servers.variables.save')}
                </m.button>
            )}
        </m.div>
    );
}

export function ServerVariables({ serverId, canEdit = true }: ServerVariablesProps) {
    const { t } = useTranslation();
    const { variables, isLoading, updateVariable, isUpdating } = useStartupVariables(serverId);

    if (isLoading) {
        return (
            <GlassCard className="p-6">
                <div className="flex items-center justify-center py-8">
                    <Spinner size="md" />
                </div>
            </GlassCard>
        );
    }

    if (variables.length === 0) {
        return null;
    }

    return (
        <GlassCard className="p-4 sm:p-6 space-y-4">
            <div className="flex items-center justify-between gap-2 flex-wrap">
                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">
                    {t('servers.variables.title')}
                </h2>
                <div className="flex items-center gap-2">
                    {!canEdit && (
                        <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded"
                            style={{ background: 'rgba(var(--color-text-muted-rgb, 100 116 139), 0.15)', color: 'var(--color-text-secondary)' }}>
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            {t('servers.files.read_only')}
                        </span>
                    )}
                    <span className="text-xs text-[var(--color-text-muted)]">
                        {t('servers.variables.count', { count: variables.length })}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2">
                {variables.map((v) => (
                    <VariableCard
                        key={v.env_variable}
                        variable={v}
                        onSave={(key, value) => updateVariable({ key, value })}
                        isSaving={isUpdating}
                        canEdit={canEdit}
                    />
                ))}
            </div>
        </GlassCard>
    );
}
