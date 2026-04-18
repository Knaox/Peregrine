import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';

export function LoadingScreen() {
    const { t } = useTranslation();

    return (
        <div className="min-h-screen bg-[var(--color-background)] flex items-center justify-center">
            <m.div
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.4 }}
                className="flex flex-col items-center gap-6"
            >
                {/* Animated spinner with glow */}
                <div className="relative">
                    <div
                        className="h-12 w-12 animate-spin rounded-full border-[3px] border-[var(--color-border)]"
                        style={{ borderTopColor: 'var(--color-primary)' }}
                    />
                    <div
                        className="absolute inset-0 rounded-full animate-ping opacity-20"
                        style={{ border: '2px solid var(--color-primary)' }}
                    />
                </div>
                <m.p
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.3 }}
                    className="text-[var(--color-text-secondary)] text-sm font-medium"
                >
                    {t('common.loading')}
                </m.p>
            </m.div>
        </div>
    );
}
