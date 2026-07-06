import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { GlassCard } from '@/components/ui/GlassCard';
import { useStartupCommand } from '@/hooks/useStartupCommand';
import type { StartupCommandSelectorProps } from '@/components/server/StartupCommandSelector.props';
import { useNamespace } from '@/i18n/useNamespace';

const selectClasses = (editable: boolean) =>
    clsx(
        'w-full px-3 py-2 text-sm sm:max-w-md',
        'bg-[var(--color-surface)] rounded-[var(--radius)]',
        'text-[var(--color-text-primary)]',
        'border border-[var(--color-border)] transition-all duration-[var(--transition-fast)]',
        'focus:outline-none focus:ring-2 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
        !editable && 'opacity-50 cursor-not-allowed',
    );

/**
 * Startup command picker (Pelican beta26+ "multiple startup commands").
 * The player switches between the egg-defined named commands; the change
 * applies immediately (like Pelican's client area) and Wings uses it on the
 * next server start. An admin-customized command (not in the egg map) is
 * shown read-only — switching away from it would make it unrecoverable.
 */
export function StartupCommandSelector({ serverId, canEdit }: StartupCommandSelectorProps) {
    useNamespace(['server-shell'] as const);
    const { t } = useTranslation();
    const { data, isLoading, switchCommand, isSwitching, switchFailed, switchSucceeded } = useStartupCommand(serverId);
    const [showSaved, setShowSaved] = useState(false);

    // Transient "applied ✓" confirmation after a successful switch.
    useEffect(() => {
        if (switchSucceeded && !isSwitching) {
            setShowSaved(true);
            const timer = setTimeout(() => setShowSaved(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [switchSucceeded, isSwitching]);

    if (isLoading || !data || (data.options.length === 0 && !data.is_custom)) {
        return null;
    }

    return (
        <GlassCard className='p-4 sm:p-6'>
            <div className='flex items-center justify-between gap-2 flex-wrap'>
                <h2 className='text-lg font-semibold text-[var(--color-text-primary)]'>
                    {t('server-shell:startup_command.title')}
                </h2>
                {data.is_custom && (
                    <span
                        className='inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded'
                        style={{ background: 'rgba(var(--color-info-rgb), 0.12)', color: 'var(--color-info)' }}
                    >
                        {t('server-shell:startup_command.custom_badge')}
                    </span>
                )}
            </div>

            <div className='mt-3 space-y-3'>
                {data.is_custom ? (
                    <p className='text-xs' style={{ color: 'var(--color-text-muted)' }}>
                        {t('server-shell:startup_command.custom_hint')}
                    </p>
                ) : (
                    <div className='space-y-1.5'>
                        <select
                            value={data.current_name ?? ''}
                            aria-label={t('server-shell:startup_command.title')}
                            disabled={!canEdit || isSwitching}
                            onChange={(event) => switchCommand(event.target.value)}
                            className={selectClasses(canEdit && !isSwitching)}
                        >
                            {data.options.map((option) => (
                                <option key={option.name} value={option.name}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <p className='text-xs' style={{ color: 'var(--color-text-muted)' }}>
                            {t('server-shell:startup_command.hint')}
                        </p>
                    </div>
                )}

                <code
                    className='block w-full rounded-[var(--radius)] px-3 py-2 text-xs whitespace-pre-wrap break-all'
                    style={{ background: 'rgba(127,127,127,.10)', color: 'var(--color-text-secondary)' }}
                >
                    {data.current}
                </code>

                {showSaved && (
                    <p className='text-xs font-medium' style={{ color: 'var(--color-success)' }} role='status'>
                        {t('server-shell:startup_command.saved')}
                    </p>
                )}
                {switchFailed && !isSwitching && !showSaved && (
                    <p className='text-xs font-medium' style={{ color: 'var(--color-danger)' }} role='alert'>
                        {t('server-shell:startup_command.error')}
                    </p>
                )}
            </div>
        </GlassCard>
    );
}
