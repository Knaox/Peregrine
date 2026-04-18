import { useCallback, useEffect, useRef } from 'react';

interface Particle {
    x: number; y: number; vx: number; vy: number;
    r: number; active: boolean; phase: number;
}

/**
 * Animated background with:
 * 1. Blurred gradient orbs (ambient color)
 * 2. Dark overlay
 * 3. Noise texture
 * 4. Living constellation — particles connected by lines, drifting like molecules
 */
export function AnimatedBackground() {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const mouseRef = useRef({ x: -1000, y: -1000 });
    const particlesRef = useRef<Particle[]>([]);
    const rafRef = useRef(0);
    const timeRef = useRef(0);

    const initParticles = useCallback((w: number, h: number) => {
        const count = Math.min(55, Math.floor((w * h) / 28000));
        const p: Particle[] = [];
        for (let i = 0; i < count; i++) {
            p.push({
                x: Math.random() * w, y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.25, vy: (Math.random() - 0.5) * 0.25,
                r: 1.2 + Math.random() * 1.5, active: Math.random() < 0.12,
                phase: Math.random() * Math.PI * 2,
            });
        }
        particlesRef.current = p;
    }, []);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d', { alpha: true });
        if (!ctx) return;

        const resize = () => {
            const dpr = Math.min(window.devicePixelRatio, 2);
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            canvas.style.width = `${window.innerWidth}px`;
            canvas.style.height = `${window.innerHeight}px`;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            if (particlesRef.current.length === 0) initParticles(window.innerWidth, window.innerHeight);
        };
        resize();
        window.addEventListener('resize', resize);

        const style = getComputedStyle(document.documentElement);
        const pRgb = style.getPropertyValue('--color-primary-rgb').trim() || '225,29,72';
        const mRgb = style.getPropertyValue('--color-text-secondary-rgb').trim() || '139,132,158';
        const maxDist = 130;

        const animate = (now: number) => {
            const dt = Math.min((now - timeRef.current) / 16, 3);
            timeRef.current = now;
            const w = window.innerWidth;
            const h = window.innerHeight;
            ctx.clearRect(0, 0, w, h);
            const particles = particlesRef.current;
            const mx = mouseRef.current.x;
            const my = mouseRef.current.y;

            for (const p of particles) {
                const dx = mx - p.x, dy = my - p.y;
                const dist = Math.hypot(dx, dy);
                if (dist < 180 && dist > 1) {
                    p.vx += (dx / dist) * 0.004 * dt;
                    p.vy += (dy / dist) * 0.004 * dt;
                }
                p.x += p.vx * dt; p.y += p.vy * dt;
                if (p.x < 0) { p.x = 0; p.vx *= -1; }
                if (p.x > w) { p.x = w; p.vx *= -1; }
                if (p.y < 0) { p.y = 0; p.vy *= -1; }
                if (p.y > h) { p.y = h; p.vy *= -1; }
                p.vx *= 0.9985; p.vy *= 0.9985;
            }

            /* Lines */
            for (let i = 0; i < particles.length; i++) {
                const a = particles[i]!;
                for (let j = i + 1; j < particles.length; j++) {
                    const b = particles[j]!;
                    const d = Math.hypot(a.x - b.x, a.y - b.y);
                    if (d < maxDist) {
                        const op = (1 - d / maxDist) * 0.15;
                        ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
                        ctx.strokeStyle = `rgba(${(a.active || b.active) ? pRgb : mRgb},${op})`;
                        ctx.lineWidth = 0.6; ctx.stroke();
                    }
                }
            }

            /* Dots */
            const t = now * 0.001;
            for (const p of particles) {
                const pulse = p.active ? 0.5 + 0.5 * Math.sin(t * 1.5 + p.phase) : 0;
                const alpha = p.active ? 0.35 + pulse * 0.45 : 0.18;
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${p.active ? pRgb : mRgb},${alpha})`;
                ctx.fill();
                if (p.active && pulse > 0.3) {
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.r + 5 + pulse * 8, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(${pRgb},${pulse * 0.05})`;
                    ctx.fill();
                }
            }
            rafRef.current = requestAnimationFrame(animate);
        };
        rafRef.current = requestAnimationFrame(animate);

        const onMouse = (e: MouseEvent) => { mouseRef.current = { x: e.clientX, y: e.clientY }; };
        window.addEventListener('mousemove', onMouse, { passive: true });

        return () => {
            cancelAnimationFrame(rafRef.current);
            window.removeEventListener('resize', resize);
            window.removeEventListener('mousemove', onMouse);
        };
    }, [initParticles]);

    return (
        <div className="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
            {/* Blurred gradient orbs */}
            <div className="absolute inset-0" style={{ filter: 'blur(80px)' }}>
                <div className="absolute h-[500px] w-[500px] rounded-full"
                    style={{ top: '-10%', left: '-5%', background: 'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.3) 0%, transparent 70%)', animation: 'orb-float-1 30s ease-in-out infinite' }} />
                <div className="absolute h-[450px] w-[450px] rounded-full"
                    style={{ top: '10%', right: '-10%', background: 'radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%)', animation: 'orb-float-2 35s ease-in-out infinite' }} />
                <div className="absolute h-[400px] w-[400px] rounded-full"
                    style={{ top: '50%', left: '30%', background: 'radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%)', animation: 'orb-float-3 25s ease-in-out infinite' }} />
            </div>

            {/* Dark overlay */}
            <div className="absolute inset-0" style={{ background: 'rgba(12, 10, 20, 0.75)' }} />

            {/* Noise texture */}
            <svg className="absolute inset-0 h-full w-full opacity-[0.05]">
                <filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" /></filter>
                <rect width="100%" height="100%" filter="url(#noise)" />
            </svg>

            {/* Constellation canvas — ON TOP of overlay, visible everywhere */}
            <canvas ref={canvasRef} className="absolute inset-0" style={{ opacity: 0.6 }} />
        </div>
    );
}
