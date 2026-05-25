import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
import clsx from 'clsx';
import { GlassCard } from '@/components/ui/GlassCard';
import { Spinner } from '@/components/ui/Spinner';
import { VariableCard } from '@/components/server/VariableCard';
import { useStartupVariablesEditor } from '@/hooks/useStartupVariablesEditor';
import type { ServerVariablesProps } from '@/components/server/ServerVariables.props';
import { useNamespace } from '@/i18n/useNamespace';

/**
 * Thin container for the startup-variable editor. All state (current/original
 * values, dirty set, batch save, save-bar registration) lives in
 * {@link useStartupVariablesEditor}; this component only renders the collapsible
 * header and the grid of controlled {@link VariableCard}s. Saving happens
 * through the shared GlobalSaveBar — there is no per-card save button.
 */
export function ServerVariables({ serverId, canEdit = true }: ServerVariablesProps) {
    useNamespace(['server-files', 'server-shell']);
    const { t } = useTranslation();
    const { variables, isLoading, values, invalidKeys, dirtyKeys, onChange, reset } = useStartupVariablesEditor(
        serverId,
        canEdit,
    );
    const [open, setOpen] = useState(true);

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

    const dirtySet = new Set(dirtyKeys);

    return (
        <GlassCard className="p-4 sm:p-6">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                className={clsx(
                    'flex w-full items-center justify-between gap-2 flex-wrap',
                    'cursor-pointer text-left rounded-[var(--radius)]',
                    'transition-colors duration-[var(--transition-fast)]',
                )}
            >
                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">
                    {t('server-shell:variables.title')}
                </h2>
                <div className="flex items-center gap-2">
                    {!canEdit && (
                        <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded"
                            style={{ background: 'rgba(var(--color-text-muted-rgb, 100 116 139), 0.15)', color: 'var(--color-text-secondary)' }}>
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            {t('server-files:files.read_only')}
                        </span>
                    )}
                    <span className="text-xs text-[var(--color-text-muted)]">
                        {t('server-shell:variables.count', { count: variables.length })}
                    </span>
                    <m.svg
                        animate={{ rotate: open ? 180 : 0 }}
                        transition={{ duration: 0.2, ease: 'easeOut' }}
                        className="h-4 w-4 text-[var(--color-text-muted)]"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                    </m.svg>
                </div>
            </button>

            <AnimatePresence initial={false}>
                {open && (
                    <m.div
                        key="content"
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.25, ease: 'easeOut' }}
                        style={{ overflow: 'hidden' }}
                    >
                        <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 mt-4">
                            {variables.map((v) => (
                                <VariableCard
                                    key={v.env_variable}
                                    variable={v}
                                    value={values[v.env_variable] ?? ''}
                                    onChange={onChange}
                                    onReset={reset}
                                    isDirty={dirtySet.has(v.env_variable)}
                                    isInvalid={invalidKeys.has(v.env_variable)}
                                    canEdit={canEdit}
                                />
                            ))}
                        </div>
                    </m.div>
                )}
            </AnimatePresence>
        </GlassCard>
    );
}
