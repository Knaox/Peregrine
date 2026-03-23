/**
 * Animated gradient mesh background.
 * Uses CSS variables for colors so it adapts to the admin theme.
 */
export function AnimatedBackground() {
    return (
        <div className="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
            {/* Blur container */}
            <div className="absolute inset-0" style={{ filter: 'blur(80px)' }}>
                {/* Primary orb — top left */}
                <div
                    className="absolute h-[500px] w-[500px] rounded-full"
                    style={{
                        top: '-10%',
                        left: '-5%',
                        background: 'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.5) 0%, transparent 70%)',
                        animation: 'orb-float-1 25s ease-in-out infinite',
                    }}
                />
                {/* Info/blue orb — top right */}
                <div
                    className="absolute h-[450px] w-[450px] rounded-full"
                    style={{
                        top: '10%',
                        right: '-10%',
                        background: 'radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, transparent 70%)',
                        animation: 'orb-float-2 30s ease-in-out infinite',
                    }}
                />
                {/* Violet orb — center */}
                <div
                    className="absolute h-[400px] w-[400px] rounded-full"
                    style={{
                        top: '50%',
                        left: '30%',
                        background: 'radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%)',
                        animation: 'orb-float-3 20s ease-in-out infinite',
                    }}
                />
                {/* Cyan orb — bottom right */}
                <div
                    className="absolute h-[350px] w-[350px] rounded-full"
                    style={{
                        top: '60%',
                        right: '20%',
                        background: 'radial-gradient(circle, rgba(6, 182, 212, 0.25) 0%, transparent 70%)',
                        animation: 'orb-float-1 35s ease-in-out infinite reverse',
                    }}
                />
                {/* Rose/primary orb — bottom left */}
                <div
                    className="absolute h-[300px] w-[300px] rounded-full"
                    style={{
                        bottom: '10%',
                        left: '-5%',
                        background: 'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.25) 0%, transparent 70%)',
                        animation: 'orb-float-2 22s ease-in-out infinite reverse',
                    }}
                />
            </div>

            {/* Dark overlay */}
            <div
                className="absolute inset-0"
                style={{ background: 'rgba(12, 10, 20, 0.75)' }}
            />

            {/* Noise texture */}
            <svg className="absolute inset-0 h-full w-full opacity-[0.08]">
                <filter id="noise">
                    <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#noise)" />
            </svg>

            {/* Dot grid */}
            <div
                className="absolute inset-0 opacity-[0.04]"
                style={{
                    backgroundImage: 'radial-gradient(circle, rgba(148, 130, 170, 0.8) 1px, transparent 1px)',
                    backgroundSize: '24px 24px',
                }}
            />
        </div>
    );
}
