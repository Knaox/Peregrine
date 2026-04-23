import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import type { ConsoleMessage } from '@/types/ConsoleMessage';
import type { Server } from '@/types/Server';

interface InstallationOverviewProps {
    server: Server;
    /** Latest install output / console messages from Wings, newest last. */
    messages: ConsoleMessage[];
    /** True if Wings has emitted `install completed` since this view mounted. */
    installCompleted: boolean;
}

/**
 * Hero card shown on the Overview page while the server is installing
 * (`server.status === 'provisioning'`).
 *
 * Replaces the normal stats / variables / actions stack with :
 *  - animated spinner + server name + egg banner backdrop
 *  - last 5 install log lines streaming live
 *  - CTA to open the full console
 *
 * Once Wings emits `install completed` the card animates into a "ready"
 * state with a "Open console" CTA — the parent page will switch back to
 * the regular overview on the next React Query refetch (status flips to
 * 'active' once the Pelican `updated: Server` webhook is processed).
 */
export function InstallationOverview({ server, messages, installCompleted }: InstallationOverviewProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();

    // Show the last few install lines for at-a-glance progress feedback.
    const recentLines = messages.slice(-6);

    return (
        <m.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: [0.4, 0, 0.2, 1] }}
            className="space-y-6"
        >
            <div
                className="relative overflow-hidden rounded-[var(--radius-xl)]"
                style={{ border: '1px solid var(--color-border)' }}
            >
                {/* Banner backdrop */}
                <div className="relative" style={{ minHeight: 320 }}>
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover opacity-50"
                        />
                    ) : (
                        <div
                            className="absolute inset-0"
                            style={{
                                background:
                                    'linear-gradient(135deg, var(--color-surface-hover), var(--color-background))',
                            }}
                        />
                    )}
                    <div
                        className="absolute inset-0"
                        style={{
                            background:
                                'linear-gradient(180deg, transparent 0%, var(--banner-overlay-soft) 35%, var(--color-background) 95%)',
                        }}
                    />

                    {/* Animated pulse halo behind the spinner */}
                    <m.div
                        aria-hidden
                        className="pointer-events-none absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full"
                        style={{
                            background:
                                'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.18) 0%, transparent 65%)',
                            filter: 'blur(40px)',
                        }}
                        animate={{ scale: [1, 1.18, 1], opacity: [0.6, 0.95, 0.6] }}
                        transition={{ duration: 3.2, ease: 'easeInOut', repeat: Infinity }}
                    />

                    <div className="relative flex flex-col items-center justify-center gap-5 px-6 py-12 text-center">
                        {/* Concentric rings */}
                        <div className="relative flex h-24 w-24 items-center justify-center">
                            <m.span
                                aria-hidden
                                className="absolute inset-0 rounded-full"
                                style={{ border: '1px solid rgba(var(--color-primary-rgb), 0.35)' }}
                                animate={{ scale: [1, 1.6], opacity: [0.55, 0] }}
                                transition={{ duration: 1.8, ease: 'easeOut', repeat: Infinity }}
                            />
                            <m.span
                                aria-hidden
                                className="absolute inset-0 rounded-full"
                                style={{ border: '1px solid rgba(var(--color-primary-rgb), 0.35)' }}
                                animate={{ scale: [1, 1.6], opacity: [0.55, 0] }}
                                transition={{ duration: 1.8, ease: 'easeOut', repeat: Infinity, delay: 0.6 }}
                            />
                            <m.div
                                className="flex h-16 w-16 items-center justify-center rounded-full"
                                style={{
                                    background: 'rgba(var(--color-primary-rgb), 0.18)',
                                    border: '1px solid rgba(var(--color-primary-rgb), 0.35)',
                                    color: 'var(--color-primary)',
                                    boxShadow: '0 0 32px rgba(var(--color-primary-rgb), 0.35)',
                                }}
                                animate={installCompleted ? {} : { rotate: 360 }}
                                transition={{ duration: 2.6, ease: 'linear', repeat: Infinity }}
                            >
                                {installCompleted ? (
                                    <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : (
                                    <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                                    </svg>
                                )}
                            </m.div>
                        </div>

                        <div className="space-y-1.5">
                            <h1
                                className="text-2xl sm:text-3xl font-extrabold text-white"
                                style={{ textShadow: '0 2px 30px rgba(0,0,0,0.6)' }}
                            >
                                {server.name}
                            </h1>
                            <p className="text-sm sm:text-base font-medium text-[var(--color-text-secondary)]">
                                {installCompleted
                                    ? t('servers.install.completed_title')
                                    : t('servers.install.in_progress_title')}
                            </p>
                            <p className="mx-auto max-w-md text-xs sm:text-sm text-[var(--color-text-muted)]">
                                {installCompleted
                                    ? t('servers.install.completed_description')
                                    : t('servers.install.in_progress_description')}
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() => navigate(`/servers/${server.id}/console`)}
                            className="cursor-pointer inline-flex items-center gap-2 rounded-[var(--radius-full)] px-4 py-2 text-sm font-semibold transition-all duration-200 hover:scale-[1.04]"
                            style={{
                                background: 'rgba(var(--color-primary-rgb), 0.18)',
                                color: 'var(--color-primary)',
                                border: '1px solid rgba(var(--color-primary-rgb), 0.35)',
                                boxShadow: '0 0 18px rgba(var(--color-primary-rgb), 0.25)',
                            }}
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 17l6-5-6-5" />
                            </svg>
                            {t('servers.install.view_console')}
                        </button>
                    </div>
                </div>
            </div>

            {/* Live tail of install output — quick at-a-glance feedback */}
            <div
                className="rounded-[var(--radius-lg)] overflow-hidden"
                style={{ border: '1px solid var(--color-border)', background: 'var(--color-background)' }}
            >
                <div
                    className="flex items-center justify-between px-3 py-2 border-b"
                    style={{ borderColor: 'var(--color-border)', background: 'var(--color-surface)' }}
                >
                    <span className="flex items-center gap-2 text-xs font-mono text-[var(--color-text-muted)]">
                        <span className="relative flex h-2 w-2">
                            <span
                                className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                style={{ background: 'var(--color-primary)' }}
                            />
                            <span
                                className="relative inline-flex h-2 w-2 rounded-full"
                                style={{ background: 'var(--color-primary)' }}
                            />
                        </span>
                        {t('servers.install.live_log_label')}
                    </span>
                    <span className="text-[10px] font-mono text-[var(--color-text-muted)] opacity-60">
                        {messages.length}
                    </span>
                </div>
                <div
                    className="p-3 font-mono text-[11px] sm:text-xs leading-relaxed"
                    style={{ minHeight: 140 }}
                >
                    {recentLines.length === 0 ? (
                        <span className="text-[var(--color-text-muted)]">
                            {t('servers.install.waiting_for_output')}
                        </span>
                    ) : (
                        recentLines.map((msg) => (
                            <div key={msg.id} className="break-all text-[var(--color-text-secondary)]">
                                {msg.text}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </m.div>
    );
}
