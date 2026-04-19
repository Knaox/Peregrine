import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { formatBytes, formatDate } from '@/utils/format';
import type { ServerInfoCardProps } from '@/components/server/ServerInfoCard.props';

function InfoItem({ icon, label, children }: {
    icon: React.ReactNode; label: string; children: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-2 min-w-0">
            <div className="flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>{icon}</div>
            <div className="min-w-0">
                <div className="text-[10px] font-medium uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>{label}</div>
                <div className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>{children}</div>
            </div>
        </div>
    );
}

const EggIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>;
const PlanIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" /></svg>;
const ResourceIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>;
const CalendarIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>;

export function ServerInfoCard({ server }: ServerInfoCardProps) {
    const { t } = useTranslation();

    // Use plan if available, otherwise fall back to Pelican limits
    const ram = server.plan?.ram ?? server.limits?.memory;
    const cpu = server.plan?.cpu ?? server.limits?.cpu;
    const disk = server.plan?.disk ?? server.limits?.disk;

    const ramLabel = ram ? formatBytes(ram * 1024 * 1024) : '-';
    const cpuLabel = cpu ? `${cpu}%` : '-';
    const diskLabel = disk ? formatBytes(disk * 1024 * 1024) : '-';

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
            className="glass-card-enhanced rounded-[var(--radius-lg)] px-3 py-3 sm:px-5 sm:py-4"
        >
            <div className="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-center sm:gap-x-8 sm:gap-y-3">
                {server.egg && (
                    <InfoItem icon={EggIcon} label={t('servers.detail.game')}>
                        <div className="flex items-center gap-1.5">
                            {server.egg.banner_image && <img src={server.egg.banner_image} alt="" className="h-4 w-4 rounded object-cover" />}
                            {server.egg.name}
                        </div>
                    </InfoItem>
                )}
                {server.plan && (
                    <InfoItem icon={PlanIcon} label={t('servers.detail.plan')}>
                        {server.plan.name}
                    </InfoItem>
                )}
                <InfoItem icon={ResourceIcon} label={t('servers.detail.allocated')}>
                    <div className="flex gap-1">
                        <span className="rounded px-1.5 py-0.5 text-[10px] font-medium" style={{ background: 'rgba(var(--color-info-rgb), 0.12)', color: 'var(--color-info)' }}>{ramLabel}</span>
                        <span className="rounded px-1.5 py-0.5 text-[10px] font-medium" style={{ background: 'rgba(var(--color-primary-rgb), 0.12)', color: 'var(--color-primary-hover)' }}>{cpuLabel}</span>
                        <span className="rounded px-1.5 py-0.5 text-[10px] font-medium" style={{ background: 'rgba(var(--color-accent-rgb), 0.12)', color: 'var(--color-accent)' }}>{diskLabel}</span>
                    </div>
                </InfoItem>
                <InfoItem icon={CalendarIcon} label={t('servers.detail.created')}>
                    <span className="text-xs" style={{ opacity: 0.7 }}>{formatDate(server.created_at)}</span>
                </InfoItem>
            </div>
        </m.div>
    );
}
