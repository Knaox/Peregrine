/**
 * Animated background with floating gradient orbs and grid overlay.
 * Renders behind all content. Uses pure CSS animations for performance.
 */
export function AnimatedBackground() {
    return (
        <div className="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
            {/* Animated gradient orbs */}
            <div
                className="absolute -left-1/4 top-0 h-[600px] w-[600px] rounded-full opacity-[0.035]"
                style={{
                    background: 'radial-gradient(circle, var(--color-primary) 0%, transparent 70%)',
                    animation: 'orb-float-1 25s ease-in-out infinite',
                }}
            />
            <div
                className="absolute -right-1/4 top-1/3 h-[500px] w-[500px] rounded-full opacity-[0.025]"
                style={{
                    background: 'radial-gradient(circle, var(--color-info) 0%, transparent 70%)',
                    animation: 'orb-float-2 30s ease-in-out infinite',
                }}
            />
            <div
                className="absolute bottom-0 left-1/3 h-[400px] w-[400px] rounded-full opacity-[0.02]"
                style={{
                    background: 'radial-gradient(circle, #8b5cf6 0%, transparent 70%)',
                    animation: 'orb-float-3 20s ease-in-out infinite',
                }}
            />

            {/* Subtle grid pattern */}
            <div
                className="absolute inset-0 opacity-[0.03]"
                style={{
                    backgroundImage: `
                        linear-gradient(rgba(148, 163, 184, 0.3) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(148, 163, 184, 0.3) 1px, transparent 1px)
                    `,
                    backgroundSize: '60px 60px',
                }}
            />

            {/* Vignette overlay */}
            <div
                className="absolute inset-0"
                style={{
                    background: 'radial-gradient(ellipse at center, transparent 50%, var(--color-background) 100%)',
                }}
            />
        </div>
    );
}
