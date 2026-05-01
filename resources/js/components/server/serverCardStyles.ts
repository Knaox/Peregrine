import clsx from 'clsx';
import type { CardConfig } from '@/hooks/useCardConfig';

interface BuildCardClassNameInput {
    config: CardConfig;
    hasBanner: boolean;
    isSelected: boolean;
    isDragging: boolean;
}

const DENSITY_CLASS: Record<CardConfig['card_density'], string> = {
    compact: 'min-h-[6rem] sm:h-28',
    comfortable: 'min-h-[8rem] sm:h-36',
    spacious: 'min-h-[10rem] sm:h-44',
};

const HOVER_CLASS: Record<CardConfig['card_hover_effect'], string> = {
    lift: 'transition-[transform,box-shadow,border-color] duration-300 hover:-translate-y-0.5 hover:shadow-[var(--shadow-lg)]',
    scale: 'scale-on-hover transition-[box-shadow,border-color] duration-300',
    glow: 'transition-[box-shadow,border-color] duration-300 hover:shadow-[var(--shadow-lg),0_0_28px_var(--color-primary-glow)]',
    none: 'transition-[box-shadow,border-color] duration-300',
};

/**
 * Returns the className stack for the ServerCard root. Composing here keeps
 * ServerCard.tsx free of inline clsx noise and isolates the
 * style→className mapping in one testable place.
 */
export function buildServerCardClassName({
    config,
    hasBanner,
    isSelected,
    isDragging,
}: BuildCardClassNameInput): string {
    const noBannerStyleClass =
        !hasBanner
            ? config.card_style === 'glass'
                ? 'border border-[var(--color-glass-border)] backdrop-blur-md hover:border-[var(--color-border-hover)]'
                : config.card_style === 'elevated'
                    ? 'bg-[var(--color-surface-elevated)] border border-[var(--color-border)] shadow-[var(--shadow-md)]'
                    : config.card_style === 'minimal'
                        ? 'bg-transparent border border-[var(--color-border)]/40 hover:bg-[var(--color-surface-hover)]/50'
                        : 'bg-[var(--color-surface)] border border-[var(--color-border)] hover:border-[var(--color-border-hover)]'
            : 'border border-transparent hover:border-[var(--color-border-hover)]';

    return clsx(
        'group relative cursor-pointer overflow-hidden rounded-[var(--radius-lg)]',
        DENSITY_CLASS[config.card_density],
        HOVER_CLASS[config.card_hover_effect],
        config.card_border_style !== 'none' && 'themed-border',
        config.card_accent_strength !== 'none' && 'border-glow',
        isDragging && 'opacity-50',
        isSelected && 'ring-2 ring-[var(--color-primary)] ring-offset-1 ring-offset-[var(--color-background)]',
        noBannerStyleClass,
    );
}

/**
 * Returns the inline style object for the ServerCard root. Centralises the
 * background colour for glass + the lifecycle accent border so the JSX
 * tree stays declarative.
 */
export function buildServerCardStyle({
    config,
    hasBanner,
    isSuspended,
    isProvisioning,
}: {
    config: CardConfig;
    hasBanner: boolean;
    isSuspended: boolean;
    isProvisioning: boolean;
}): React.CSSProperties {
    const style: React.CSSProperties = {};
    if (config.card_style === 'glass' && !hasBanner) {
        style.background = 'var(--color-glass)';
    }
    if (config.card_header_style === 'solid' && !hasBanner) {
        style.background = 'var(--color-surface-elevated)';
    }
    // Lifecycle accent — keep the existing 3px left bar for suspended /
    // provisioning. `accent-left` border style adds a 3px primary bar by
    // default, but lifecycle colours always win when active so the user
    // can spot a degraded server at a glance.
    if (isSuspended) {
        style.borderLeft = '3px solid var(--color-suspended)';
    } else if (isProvisioning) {
        style.borderLeft = '3px solid var(--color-installing)';
    } else if (config.card_border_style === 'accent-left') {
        style.borderLeft = '3px solid var(--color-primary)';
    }
    return style;
}
