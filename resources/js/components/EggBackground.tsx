import { AnimatedBackground } from '@/components/AnimatedBackground';

interface EggBackgroundProps {
    imageUrl?: string | null;
    opacity?: number;
}

/**
 * Contextual background for server pages.
 * - With egg image: renders the image at visible opacity (no AnimatedBackground underneath to avoid overlay stacking)
 * - Without egg image: falls back to AnimatedBackground orbs
 */
export function EggBackground({ imageUrl, opacity = 0.25 }: EggBackgroundProps) {
    if (!imageUrl) {
        return <AnimatedBackground />;
    }

    return (
        <div className="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
            {/* Egg image — visible, desaturated to blend with dark theme */}
            <img
                src={imageUrl}
                alt=""
                className="absolute inset-0 h-full w-full object-cover"
                style={{ opacity, mixBlendMode: 'luminosity' }}
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
