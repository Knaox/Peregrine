import { useEffect, useRef, useState } from 'react';

/**
 * Animates a number from 0 to `target` over `duration` ms.
 * Only triggers on mount or when target changes by more than 10%.
 */
export function useCountUp(target: number, duration = 600): number {
    const [value, setValue] = useState(0);
    const prevTarget = useRef(0);
    const frameRef = useRef(0);

    useEffect(() => {
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
    }, [target, duration]);

    return value;
}
