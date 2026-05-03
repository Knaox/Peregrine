import { memo } from 'react';

interface BentoStatusRingProps {
    /** 0..100 — CPU usage percentage. Drives the arc length. */
    pct: number;
    /** Resolved CSS color string (`var(--color-success)` etc.). */
    color: string;
    /** Pixel diameter of the ring. Defaults to 28 (fits the small tile). */
    size?: number;
    /** Animate the inner dot for "alive" servers. */
    isAlive?: boolean;
    /** When true the ring renders empty (e.g. inactive server, no stats). */
    isInactive?: boolean;
}

/**
 * Circular progress ring around a status dot — the "vital sign" for a
 * Bento tile. Encodes CPU% as the arc fill so a tile with no room for
 * a stats label still communicates load at a glance. Health colour
 * lives in the dot AND the arc, so colour-blind users still get the
 * state via the dot's vertical position (paired with the name nearby).
 */
function BentoStatusRingImpl({ pct, color, size = 28, isAlive = false, isInactive = false }: BentoStatusRingProps) {
    const half = size / 2;
    const stroke = Math.max(2, size / 12);
    const radius = half - stroke;
    const circumference = 2 * Math.PI * radius;
    const dash = isInactive ? 0 : (Math.min(100, Math.max(0, pct)) / 100) * circumference;

    return (
        <svg
            width={size}
            height={size}
            viewBox={`0 0 ${size} ${size}`}
            className="drop-shadow-[0_0_4px_rgba(0,0,0,0.4)]"
            aria-hidden
        >
            <circle
                cx={half}
                cy={half}
                r={radius}
                fill="rgba(0,0,0,0.35)"
                stroke="rgba(255,255,255,0.18)"
                strokeWidth={stroke}
            />
            {!isInactive && (
                <circle
                    cx={half}
                    cy={half}
                    r={radius}
                    fill="none"
                    stroke={color}
                    strokeWidth={stroke}
                    strokeDasharray={`${dash} ${circumference}`}
                    strokeLinecap="round"
                    transform={`rotate(-90 ${half} ${half})`}
                    style={{ transition: 'stroke-dasharray 600ms ease-out' }}
                />
            )}
            <circle
                cx={half}
                cy={half}
                r={Math.max(2, size / 7)}
                fill={color}
                opacity={isInactive ? 0.5 : 1}
                className={isAlive ? 'pulse-ring-dot' : undefined}
            />
        </svg>
    );
}

export const BentoStatusRing = memo(BentoStatusRingImpl);
