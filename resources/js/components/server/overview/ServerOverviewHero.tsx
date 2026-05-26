import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { formatUptime } from '@/utils/format';
import type { Server } from '@/types/Server';
import { useNamespace } from '@/i18n/useNamespace';

interface ServerOverviewHeroProps {
    server: Server;
    state: string;
    statusLabel: string;
    isRunningState: boolean;
    uptime: number | undefined;
    address: string | null;
    copied: boolean;
    onCopy: () => void;
    canPower: boolean;
    canStart: boolean;
    canStop: boolean;
    canRestart: boolean;
}

const CHIP = 'rgba(0,0,0,0.42)';
const chipStyle: React.CSSProperties = {
    background: CHIP, backdropFilter: 'blur(10px)', border: '1px solid rgba(255,255,255,0.15)', textShadow: '0 1px 4px rgba(0,0,0,0.4)',
};

/**
 * Server overview hero. Game art shows at full opacity behind a LIGHT
 * bottom-anchored scrim (best practice: localise the dark gradient to the
 * text zone, ~0.6→transparent — see NN/G) so the artwork pops while the
 * white title stays legible in both themes. No artwork → a lively branded
 * gradient. A drifting halo + faint grid keep it alive.
 */
export function ServerOverviewHero({
    server, state, statusLabel, isRunningState, uptime, address, copied, onCopy,
    canPower, canStart, canStop, canRestart,
}: ServerOverviewHeroProps) {
    useNamespace(["server-overview"] as const);
    const { t } = useTranslation();
    const hasArt = !!server.egg?.banner_image;

    return (
        <m.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, ease: [0.34, 1.56, 0.64, 1] }}
            className="relative overflow-hidden rounded-[var(--radius-xl)]"
            style={{ border: '1px solid var(--color-border)' }}>
            <div className="relative" style={{ minHeight: 210 }}>
                {hasArt && (
                    <m.img src={server.egg!.banner_image!} alt={server.egg!.name}
                        className="absolute inset-0 h-full w-full object-cover"
                        initial={{ scale: 1.08 }} animate={{ scale: 1 }} transition={{ duration: 0.8, ease: 'easeOut' }} />
                )}
                {/* Light bottom scrim over artwork; branded gradient when none. */}
                <div className="absolute inset-0" style={{
                    background: hasArt
                        ? 'linear-gradient(to top, rgba(0,0,0,0.66) 0%, rgba(0,0,0,0.30) 30%, rgba(0,0,0,0) 62%)'
                        : 'linear-gradient(120deg, var(--color-primary) 0%, color-mix(in srgb, var(--color-primary) 42%, #0a0a12) 100%)',
                }} />
                <div aria-hidden className="biome-hero-halo pointer-events-none absolute -right-16 -top-24 h-64 w-64" />
                <div aria-hidden className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{ backgroundImage: 'linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px)', backgroundSize: '34px 34px' }} />

                <div className="relative flex h-full flex-col justify-between p-4 sm:p-5 md:p-6" style={{ minHeight: 200 }}>
                    <m.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.2 }}
                        className="flex items-center gap-3 flex-wrap">
                        <span className="relative flex items-center gap-2 rounded-full text-sm font-medium px-3.5 py-1.5"
                            style={{
                                background: isRunningState ? 'rgba(var(--color-success-rgb), 0.18)' : 'rgba(0,0,0,0.42)',
                                color: isRunningState ? 'var(--color-success)' : '#fff',
                                backdropFilter: 'blur(12px)',
                                border: `1px solid ${isRunningState ? 'rgba(var(--color-success-rgb), 0.25)' : 'rgba(255,255,255,0.15)'}`,
                            }}>
                            <span className="relative flex h-2.5 w-2.5">
                                {isRunningState && <span className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style={{ background: 'var(--color-success)' }} />}
                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full" style={{ background: isRunningState ? 'var(--color-success)' : 'var(--color-text-muted)' }} />
                            </span>
                            {statusLabel}
                        </span>
                        {isRunningState && uptime != null && uptime > 0 && (
                            <span className="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-mono text-white" style={chipStyle}>
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {formatUptime(uptime)}
                            </span>
                        )}
                    </m.div>

                    <div className="space-y-3">
                        <m.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3, duration: 0.5 }}
                            className="text-xl sm:text-3xl md:text-4xl font-extrabold text-white" style={{ textShadow: '0 2px 30px rgba(0,0,0,0.6)' }}>
                            {server.name}
                        </m.h1>
                        <m.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4 }}
                            className="flex flex-wrap items-center gap-2 sm:gap-3">
                            {address && (
                                <button type="button" onClick={onCopy}
                                    className="inline-flex items-center gap-2 rounded-full text-sm text-white cursor-pointer transition-all duration-200 hover:scale-[1.03]"
                                    style={{ ...chipStyle, padding: '6px 14px' }}>
                                    <svg className="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" /></svg>
                                    <span>{copied ? t('server-overview:list.copied') : address}</span>
                                </button>
                            )}
                            {server.egg && (
                                <span className="inline-flex items-center gap-1.5 rounded-full text-sm text-white" style={{ ...chipStyle, padding: '4px 14px' }}>
                                    {server.egg.banner_image && <img src={server.egg.banner_image} alt="" className="h-4 w-4 rounded object-cover" />}
                                    {server.egg.name}
                                </span>
                            )}
                            {canPower && (
                                <div className="sm:ml-auto"><ServerPowerControls serverId={server.id} state={state} canStart={canStart} canStop={canStop} canRestart={canRestart} /></div>
                            )}
                        </m.div>
                    </div>
                </div>
            </div>
        </m.div>
    );
}
