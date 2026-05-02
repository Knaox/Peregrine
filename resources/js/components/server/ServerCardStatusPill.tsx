import clsx from 'clsx';
import { useTranslation } from 'react-i18next';
import type { CardConfig } from '@/hooks/useCardConfig';

interface ServerCardStatusPillProps {
    /** Lifecycle state — the pill only renders for `suspended` / `provisioning`. */
    state: string;
    position: CardConfig['card_status_position'];
}

const SUSPEND_VARS = {
    bg: 'rgba(var(--color-suspended-rgb), 0.18)',
    fg: 'var(--color-suspended)',
    border: 'rgba(var(--color-suspended-rgb), 0.35)',
} as const;

const INSTALL_VARS = {
    bg: 'rgba(var(--color-installing-rgb), 0.18)',
    fg: 'var(--color-installing)',
    border: 'rgba(var(--color-installing-rgb), 0.35)',
} as const;

const POSITION_CLASSES: Record<CardConfig['card_status_position'], string> = {
    inline: 'static z-20 inline-flex',
    'top-right': 'absolute right-2 top-2 z-30 inline-flex',
    'top-left': 'absolute left-2 top-2 z-30 inline-flex',
    'corner-ribbon': 'absolute right-0 top-0 z-30 inline-flex rounded-bl-lg !rounded-tr-lg',
};

const SuspendIcon = (
    <svg className="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
        <rect x="5" y="11" width="14" height="10" rx="2" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0v4" />
    </svg>
);

const InstallIcon = (
    <svg className="h-2.5 w-2.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
    </svg>
);

/**
 * Discreet lifecycle pill — reuses theme colors so admins can match brand.
 * Position is admin-driven via card_status_position. The `corner-ribbon`
 * variant uses tighter padding + explicit corner radii to read as a ribbon
 * pinned to the card corner; other variants are pill-shaped capsules.
 */
export function ServerCardStatusPill({ state, position }: ServerCardStatusPillProps) {
    const { t } = useTranslation();
    const isSuspended = state === 'suspended';
    const isProvisioning = state === 'provisioning' || state === 'provisioning_failed';

    if (!isSuspended && !isProvisioning) return null;

    const v = isSuspended ? SUSPEND_VARS : INSTALL_VARS;
    const labelKey = isSuspended ? 'servers.status.suspended' : 'servers.status.provisioning';
    const isRibbon = position === 'corner-ribbon';

    return (
        <div
            className={clsx(
                POSITION_CLASSES[position],
                'items-center gap-1 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider',
                isRibbon ? 'rounded-bl-lg' : 'rounded-full',
            )}
            style={{
                background: v.bg,
                color: v.fg,
                border: `1px solid ${v.border}`,
                backdropFilter: 'blur(4px)',
            }}
            title={t(labelKey)}
        >
            {isSuspended ? SuspendIcon : InstallIcon}
            {t(labelKey)}
        </div>
    );
}
