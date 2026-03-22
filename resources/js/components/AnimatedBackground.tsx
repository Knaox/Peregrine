/**
 * Animated gradient mesh background — Aceternity-inspired pattern.
 * Uses large colorful blobs with CSS blur + mix-blend-mode + slow animations.
 * The blur makes them soft; opacity is HIGH (0.6-0.8) so they're VISIBLE.
 */
export function AnimatedBackground() {
    return (
        <div className="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
            {/* Blur container — all children are blurred together */}
            <div className="absolute inset-0" style={{ filter: 'blur(80px)' }}>
                {/* Orange primary orb — top left */}
                <div
                    className="absolute h-[500px] w-[500px] rounded-full"
                    style={{
                        top: '-10%',
                        left: '-5%',
                        background: 'radial-gradient(circle, rgba(249, 115, 22, 0.7) 0%, transparent 70%)',
                        animation: 'orb-float-1 25s ease-in-out infinite',
                    }}
                />
                {/* Blue orb — top right */}
                <div
                    className="absolute h-[450px] w-[450px] rounded-full"
                    style={{
                        top: '10%',
                        right: '-10%',
                        background: 'radial-gradient(circle, rgba(59, 130, 246, 0.5) 0%, transparent 70%)',
                        animation: 'orb-float-2 30s ease-in-out infinite',
                    }}
                />
                {/* Violet orb — center */}
                <div
                    className="absolute h-[400px] w-[400px] rounded-full"
                    style={{
                        top: '50%',
                        left: '30%',
                        background: 'radial-gradient(circle, rgba(139, 92, 246, 0.4) 0%, transparent 70%)',
                        animation: 'orb-float-3 20s ease-in-out infinite',
                    }}
                />
                {/* Cyan orb — bottom right */}
                <div
                    className="absolute h-[350px] w-[350px] rounded-full"
                    style={{
                        bottom: '-5%',
                        right: '20%',
                        background: 'radial-gradient(circle, rgba(6, 182, 212, 0.35) 0%, transparent 70%)',
                        animation: 'orb-float-1 35s ease-in-out infinite reverse',
                    }}
                />
                {/* Rose orb — bottom left */}
                <div
                    className="absolute h-[300px] w-[300px] rounded-full"
                    style={{
                        bottom: '10%',
                        left: '-5%',
                        background: 'radial-gradient(circle, rgba(244, 63, 94, 0.3) 0%, transparent 70%)',
                        animation: 'orb-float-2 22s ease-in-out infinite reverse',
                    }}
                />
            </div>

            {/* Dark overlay to tame the colors — keeps it subtle but visible */}
            <div
                className="absolute inset-0"
                style={{ background: 'rgba(12, 15, 26, 0.75)' }}
            />

            {/* Noise texture overlay for depth */}
            <svg className="absolute inset-0 h-full w-full opacity-[0.08]">
                <filter id="noise">
                    <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#noise)" />
            </svg>

            {/* Subtle dot grid */}
            <div
                className="absolute inset-0 opacity-[0.04]"
                style={{
                    backgroundImage: 'radial-gradient(circle, rgba(148, 163, 184, 0.8) 1px, transparent 1px)',
                    backgroundSize: '24px 24px',
                }}
            />
        </div>
    );
}
