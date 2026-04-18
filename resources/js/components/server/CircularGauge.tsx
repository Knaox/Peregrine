import { useState, useEffect } from 'react';

interface CircularGaugeProps {
    value: number;
    max: number;
    color: string;
    label: string;
    sublabel?: string;
    size?: number;
    strokeWidth?: number;
}

function getGaugeColor(percent: number, defaultColor: string): string {
    if (percent > 90) return 'var(--color-danger)';
    if (percent > 75) return 'var(--color-warning)';
    return defaultColor;
}

/**
 * Animated circular gauge with SVG.
 * Uses spring-like CSS transition for a natural fill effect.
 * Glow intensity increases with value for visual urgency.
 */
export function CircularGauge({
    value, max, color, label, sublabel, size = 80, strokeWidth = 6,
}: CircularGaugeProps) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const percent = max > 0 ? Math.min(100, Math.max(0, (value / max) * 100)) : 0;
    const activeColor = getGaugeColor(percent, color);

    const [animatedOffset, setAnimatedOffset] = useState(circumference);

    useEffect(() => {
        const timer = requestAnimationFrame(() => {
            setAnimatedOffset(circumference - (percent / 100) * circumference);
        });
        return () => cancelAnimationFrame(timer);
    }, [circumference, percent]);

    const glowIntensity = Math.min(0.5, percent / 200);

    return (
        <div className="relative flex flex-col items-center">
            <svg width={size} height={size} className="gauge-ring">
                {/* Background track */}
                <circle
                    cx={size / 2} cy={size / 2} r={radius}
                    fill="none" stroke="rgba(255,255,255,0.06)"
                    strokeWidth={strokeWidth} strokeLinecap="round"
                />
                {/* Active arc */}
                <circle
                    cx={size / 2} cy={size / 2} r={radius}
                    fill="none" stroke={activeColor}
                    strokeWidth={strokeWidth} strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={animatedOffset}
                    style={{
                        filter: `drop-shadow(0 0 ${4 + glowIntensity * 12}px ${activeColor})`,
                        transition: 'stroke-dashoffset 1s cubic-bezier(0.34, 1.56, 0.64, 1), stroke 500ms ease, filter 500ms ease',
                    }}
                />
            </svg>
            {/* Center label */}
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-sm font-bold" style={{ color: 'var(--color-text-primary)' }}>
                    {label}
                </span>
                {sublabel && (
                    <span className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                        {sublabel}
                    </span>
                )}
            </div>
        </div>
    );
}
