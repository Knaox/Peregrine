import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';

interface DashboardHeaderProps {
    userName?: string;
    isAdmin?: boolean;
    serverCount: number;
}

function getGreetingKey(): string {
    const hour = new Date().getHours();
    if (hour < 6) return 'nav.greeting_night';
    if (hour < 12) return 'nav.greeting_morning';
    if (hour < 18) return 'nav.greeting_afternoon';
    return 'nav.greeting_evening';
}

export function DashboardHeader({ userName, isAdmin, serverCount }: DashboardHeaderProps) {
    const { t } = useTranslation();
    const greetingKey = useMemo(getGreetingKey, []);

    return (
        <m.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: 'easeOut' }}
            className="mb-8 flex items-end justify-between"
        >
            <div>
                <m.h1
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.1, duration: 0.4 }}
                    className="text-3xl font-bold text-[var(--color-text-primary)]"
                >
                    {t(greetingKey, { name: userName ?? '' })}
                </m.h1>
                <m.p
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.25 }}
                    className="mt-1 text-[var(--color-text-secondary)]"
                >
                    {t('servers.list.title')}
                    {serverCount > 0 && (
                        <span className="ml-2 inline-flex items-center justify-center rounded-[var(--radius-full)] px-2 py-0.5 text-xs font-medium"
                            style={{ background: 'rgba(var(--color-primary-rgb), 0.1)', color: 'var(--color-primary)' }}>
                            {serverCount}
                        </span>
                    )}
                </m.p>
            </div>

            {isAdmin && (
                <m.a
                    href="/admin"
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.3, type: 'spring', stiffness: 300, damping: 20 }}
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    className={clsx(
                        'inline-flex items-center gap-2',
                        'rounded-[var(--radius-full)] px-5 py-2.5 text-sm font-medium',
                        'glass-card-enhanced border-[var(--color-primary)]/30',
                        'text-[var(--color-primary)]',
                        'transition-all duration-200',
                        'hover:border-[var(--color-primary)]/50 hover:shadow-[0_0_24px_var(--color-primary-glow)]',
                    )}
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {t('nav.settings')}
                </m.a>
            )}
        </m.div>
    );
}
