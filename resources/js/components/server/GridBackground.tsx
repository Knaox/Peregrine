import { useCallback, useEffect, useRef } from 'react';

interface Particle {
    x: number;
    y: number;
    vx: number;
    vy: number;
    r: number;
    active: boolean;
    phase: number;
}

/**
 * Animated constellation/universe background.
 * Particles drift like molecules, lines connect nearby ones dynamically.
 * Active nodes pulse with the primary color — gives an organic, living feel.
 * Canvas-based for smooth 60fps with minimal overhead.
 */
export function GridBackground() {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const mouseRef = useRef({ x: -1000, y: -1000 });
    const particlesRef = useRef<Particle[]>([]);
    const rafRef = useRef(0);
    const timeRef = useRef(0);

    const init = useCallback((w: number, h: number) => {
        const count = Math.min(60, Math.floor((w * h) / 25000));
        const particles: Particle[] = [];
        for (let i = 0; i < count; i++) {
            particles.push({
                x: Math.random() * w,
                y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                r: 1.5 + Math.random() * 1.5,
                active: Math.random() < 0.15,
                phase: Math.random() * Math.PI * 2,
            });
        }
        particlesRef.current = particles;
    }, []);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d', { alpha: true });
        if (!ctx) return;

        const resize = () => {
            const dpr = Math.min(window.devicePixelRatio, 2);
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            if (particlesRef.current.length === 0) init(rect.width, rect.height);
        };
        resize();
        window.addEventListener('resize', resize);

        const style = getComputedStyle(document.documentElement);
        const primaryRgb = style.getPropertyValue('--color-primary-rgb').trim() || '225,29,72';
        const mutedRgb = style.getPropertyValue('--color-text-secondary-rgb').trim() || '139,132,158';

        const maxDist = 140;

        const animate = (now: number) => {
            const dt = Math.min((now - timeRef.current) / 16, 3);
            timeRef.current = now;
            const rect = canvas.getBoundingClientRect();
            const w = rect.width;
            const h = rect.height;
            ctx.clearRect(0, 0, w, h);

            const particles = particlesRef.current;
            const mx = mouseRef.current.x;
            const my = mouseRef.current.y;

            /* Update positions */
            for (const p of particles) {
                /* Subtle mouse attraction */
                const dx = mx - p.x;
                const dy = my - p.y;
                const dist = Math.hypot(dx, dy);
                if (dist < 200 && dist > 1) {
                    p.vx += (dx / dist) * 0.003 * dt;
                    p.vy += (dy / dist) * 0.003 * dt;
                }

                p.x += p.vx * dt;
                p.y += p.vy * dt;

                /* Bounce off edges */
                if (p.x < 0) { p.x = 0; p.vx *= -1; }
                if (p.x > w) { p.x = w; p.vx *= -1; }
                if (p.y < 0) { p.y = 0; p.vy *= -1; }
                if (p.y > h) { p.y = h; p.vy *= -1; }

                /* Damping */
                p.vx *= 0.999;
                p.vy *= 0.999;
            }

            /* Draw connections */
            for (let i = 0; i < particles.length; i++) {
                const a = particles[i];
                if (!a) continue;
                for (let j = i + 1; j < particles.length; j++) {
                    const b = particles[j];
                    if (!b) continue;
                    const d = Math.hypot(a.x - b.x, a.y - b.y);
                    if (d < maxDist) {
                        const opacity = (1 - d / maxDist) * 0.12;
                        const rgb = (a.active || b.active) ? primaryRgb : mutedRgb;
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.strokeStyle = `rgba(${rgb},${opacity})`;
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }

            /* Draw particles */
            const t = now * 0.001;
            for (const p of particles) {
                const pulse = p.active ? 0.5 + 0.5 * Math.sin(t * 1.5 + p.phase) : 0;
                const alpha = p.active ? 0.3 + pulse * 0.5 : 0.15;
                const rgb = p.active ? primaryRgb : mutedRgb;

                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${rgb},${alpha})`;
                ctx.fill();

                /* Glow for active particles */
                if (p.active && pulse > 0.3) {
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r + 4 + pulse * 6, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(${primaryRgb},${pulse * 0.06})`;
                    ctx.fill();
                }
            }

            rafRef.current = requestAnimationFrame(animate);
        };

        rafRef.current = requestAnimationFrame(animate);

        const onMouseMove = (e: MouseEvent) => {
            const rect = canvas.getBoundingClientRect();
            mouseRef.current = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        };
        const onMouseLeave = () => { mouseRef.current = { x: -1000, y: -1000 }; };

        window.addEventListener('mousemove', onMouseMove, { passive: true });
        canvas.addEventListener('mouseleave', onMouseLeave);

        return () => {
            cancelAnimationFrame(rafRef.current);
            window.removeEventListener('resize', resize);
            window.removeEventListener('mousemove', onMouseMove);
            canvas.removeEventListener('mouseleave', onMouseLeave);
        };
    }, [init]);

    return (
        <canvas
            ref={canvasRef}
            className="pointer-events-none absolute inset-0 z-0 h-full w-full"
            aria-hidden="true"
            style={{ opacity: 0.5 }}
        />
    );
}
