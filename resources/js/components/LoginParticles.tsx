import { useMemo } from 'react';

interface Particle {
    left: string;
    size: number;
    delay: number;
    duration: number;
    opacity: number;
}

/**
 * Floating particles for the login page background.
 * Uses pure CSS animation — no JS runtime cost.
 */
export function LoginParticles() {
    const particles = useMemo<Particle[]>(() => {
        const count = 20;
        return Array.from({ length: count }, (_, i) => ({
            left: `${(i / count) * 100 + Math.random() * 5}%`,
            size: 2 + Math.random() * 3,
            delay: Math.random() * 15,
            duration: 12 + Math.random() * 18,
            opacity: 0.15 + Math.random() * 0.25,
        }));
    }, []);

    return (
        <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
            {particles.map((p, i) => (
                <div
                    key={i}
                    className="absolute bottom-0 rounded-full"
                    style={{
                        left: p.left,
                        width: p.size,
                        height: p.size,
                        background: `rgba(var(--color-primary-rgb), ${p.opacity})`,
                        boxShadow: `0 0 ${p.size * 3}px rgba(var(--color-primary-rgb), ${p.opacity * 0.5})`,
                        animation: `particle-float ${p.duration}s linear ${p.delay}s infinite`,
                    }}
                />
            ))}
        </div>
    );
}
