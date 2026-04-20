import { AnimatedBackground } from '@/components/AnimatedBackground';
import { useThemeModeStore } from '@/stores/themeModeStore';

interface EggBackgroundProps {
    imageUrl?: string | null;
    opacity?: number;
    /** When true, render nothing. Used on pages that already show the banner
     * in their own hero (ServerOverviewPage) to avoid duplicated bleed. */
    disabled?: boolean;
}

/**
 * Contextual background for server pages.
 * - With egg image: renders the image full-page as a subtle ambience behind
 *   the content (mode-aware opacity + blend keeps it readable).
 * - Without egg image: falls back to AnimatedBackground orbs.
 *
 * Mode-aware opacity + blend: luminosity at 25% works on dark surfaces, multiply
 * at 15% gives a subtle pastel tint on a white page.
 */
export function EggBackground({ imageUrl, opacity, disabled = false }: EggBackgroundProps) {
    const effective = useThemeModeStore((s) => s.effective);
    const isLight = effective === 'light';

    if (disabled) {
        return null;
    }

    if (!imageUrl) {
        return <AnimatedBackground />;
    }

    const effectiveOpacity = opacity ?? (isLight ? 0.15 : 0.25);
    const blendMode: 'luminosity' | 'multiply' = isLight ? 'multiply' : 'luminosity';

    return (
        <div
            className="pointer-events-none absolute inset-0 z-0"
            aria-hidden="true"
        >
            {/* Egg image — visible but subdued; blend mode flips per theme */}
            <img
                src={imageUrl}
                alt=""
                className="absolute inset-0 h-full w-full object-cover"
                style={{ opacity: effectiveOpacity, mixBlendMode: blendMode }}
            />

            {/* Gradient overlay: fades out toward edges, preserves center visibility */}
            <div
                className="absolute inset-0"
                style={{
                    background: `
                        radial-gradient(ellipse at center, transparent 20%, var(--color-background) 85%),
                        linear-gradient(to bottom, transparent 30%, var(--color-background) 95%)
                    `,
                }}
            />

            {/* Noise texture for depth */}
            <svg className="absolute inset-0 h-full w-full opacity-[0.06]">
                <filter id="egg-noise">
                    <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#egg-noise)" />
            </svg>
        </div>
    );
}
