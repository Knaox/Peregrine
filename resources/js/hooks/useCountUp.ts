import { useEffect, useRef, useState } from 'react';

interface UseCountUpOptions {
    duration?: number;
    /** When false, returns the target value immediately without animating.
     *  Use this to globally disable count-ups in dense lists where 60fps
     *  tweens × N items × M stats become a measurable drag. */
    enabled?: boolean;
}

/**
 * Animates a number from 0 to `target` over `duration` ms.
 * Only triggers on mount or when target changes by more than 10%.
 */
export function useCountUp(target: number, options: UseCountUpOptions = {}): number {
    const { duration = 600, enabled = true } = options;
    const [value, setValue] = useState(enabled ? 0 : target);
    const prevTarget = useRef(0);
    const frameRef = useRef(0);

    useEffect(() => {
        if (!enabled) {
            prevTarget.current = target;
            setValue(target);
            return;
        }

        // Skip trivial changes (less than 10% difference)
        const diff = Math.abs(target - prevTarget.current);
        const threshold = Math.max(prevTarget.current * 0.1, 1);
        if (diff < threshold && prevTarget.current !== 0) {
            setValue(target);
            return;
        }

        const startValue = prevTarget.current;
        prevTarget.current = target;
        const startTime = performance.now();

        const animate = (now: number) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            setValue(startValue + (target - startValue) * eased);

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(animate);
            }
        };

        frameRef.current = requestAnimationFrame(animate);

        return () => {
            cancelAnimationFrame(frameRef.current);
        };
    }, [target, duration, enabled]);

    return value;
}
