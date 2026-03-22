import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';

export function ServerEmptyState() {
    const { t } = useTranslation();

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: 'easeOut' }}
            className="rounded-[var(--radius-lg)] backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)] p-16 text-center"
        >
            {/* Gradient glow behind icon */}
            <div className="relative mx-auto mb-6 h-20 w-20">
                <div className="absolute inset-0 rounded-full bg-[var(--color-primary)]/10 blur-xl" />
                <div className="relative flex h-20 w-20 items-center justify-center rounded-full bg-[var(--color-surface-hover)] shadow-[0_0_30px_var(--color-primary-glow)] animate-[float_3s_ease-in-out_infinite]">
                    <svg
                        className="h-10 w-10 text-[var(--color-text-muted)]"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={1.5}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"
                        />
                    </svg>
                </div>
            </div>
            <p className="text-sm text-[var(--color-text-secondary)]">{t('servers.list.empty')}</p>
        </m.div>
    );
}
