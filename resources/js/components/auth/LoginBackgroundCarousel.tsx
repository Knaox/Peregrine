import { useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, m } from 'motion/react';

interface LoginBackgroundCarouselProps {
    images: string[];
    /** ms between transitions. Below 1500 the crossfade looks broken. */
    interval: number;
    /** When true, picks the next image at random (skipping the current one
     *  to avoid a no-op transition). When false, cycles in array order. */
    random: boolean;
    /** 0–24 px — applied as `filter: blur()` on the image layer. */
    blur: number;
    /** 0–100 — applied as `opacity` on the image layer (under the form). */
    opacity: number;
    /** Optional brand-gradient fallback rendered behind the cycling image
     *  so a 404/missing src doesn't show as solid black during the fade. */
    fallbackGradient?: boolean;
}

const FALLBACK_GRADIENT = 'linear-gradient(135deg, var(--color-primary), var(--color-secondary))';

/**
 * Crossfading background carousel. Renders one image at a time via
 * `AnimatePresence` with key=`currentIndex` — Framer Motion handles the
 * cross-fade for us.
 *
 * Edge cases:
 *  - 0 images : renders only the fallback gradient (or null if disabled).
 *  - 1 image  : static render, no cycle (saves a setInterval).
 *  - reduced-motion : falls back to instant swap (transition: 0ms).
 */
export function LoginBackgroundCarousel({
    images,
    interval,
    random,
    blur,
    opacity,
    fallbackGradient = true,
}: LoginBackgroundCarouselProps) {
    const candidateImages = useMemo(() => images.filter((s) => s.length > 0), [images]);
    // `validImages` is the subset that actually loaded. We pre-flight each
    // path with a hidden `Image()` and silently drop any 404/decode error
    // — without this, a stale path in `theme_login_background_images`
    // (file deleted manually on disk) would render a solid-black panel
    // since `background-image: url("missing.png")` has no `onerror` event
    // path. Starts as the candidate list so the first paint is not
    // gradient-only when the paths are valid.
    const [validImages, setValidImages] = useState<string[]>(candidateImages);

    useEffect(() => {
        let cancelled = false;
        if (candidateImages.length === 0) {
            setValidImages([]);
            return () => {
                cancelled = true;
            };
        }
        const checks = candidateImages.map(
            (src) =>
                new Promise<string | null>((resolve) => {
                    const probe = new Image();
                    probe.onload = () => resolve(src);
                    probe.onerror = () => resolve(null);
                    probe.src = src;
                }),
        );
        void Promise.all(checks).then((results) => {
            if (cancelled) return;
            setValidImages(results.filter((s): s is string => s !== null));
        });
        return () => {
            cancelled = true;
        };
    }, [candidateImages]);

    const safeImages = validImages;
    const [index, setIndex] = useState(0);
    const indexRef = useRef(index);
    indexRef.current = index;

    // Keep the index in range when valid images shrink (e.g. all paths
    // turned out to be broken).
    useEffect(() => {
        setIndex((prev) => (safeImages.length > 0 ? prev % safeImages.length : 0));
    }, [safeImages.length]);

    useEffect(() => {
        if (safeImages.length < 2) return;
        const tick = () => {
            setIndex((prev) => {
                if (random) {
                    if (safeImages.length === 1) return 0;
                    let next = prev;
                    while (next === prev) {
                        next = Math.floor(Math.random() * safeImages.length);
                    }
                    return next;
                }
                return (prev + 1) % safeImages.length;
            });
        };
        const id = window.setInterval(tick, Math.max(1500, interval));
        return () => window.clearInterval(id);
    }, [safeImages.length, interval, random]);

    if (safeImages.length === 0) {
        if (!fallbackGradient) return null;
        return (
            <div
                className="absolute inset-0"
                style={{ background: FALLBACK_GRADIENT }}
                aria-hidden
            />
        );
    }

    const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const transition = reduce
        ? { duration: 0 }
        : { duration: 1.1, ease: [0.4, 0, 0.2, 1] as [number, number, number, number] };

    const current = safeImages[Math.min(index, safeImages.length - 1)] ?? '';

    return (
        <div className="absolute inset-0" aria-hidden>
            {fallbackGradient && (
                <div
                    className="absolute inset-0"
                    style={{ background: FALLBACK_GRADIENT }}
                />
            )}
            <AnimatePresence mode="sync">
                <m.div
                    key={current}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: opacity / 100 }}
                    exit={{ opacity: 0 }}
                    transition={transition}
                    className="absolute inset-0"
                    style={{
                        background: `url("${current}") center/cover no-repeat`,
                        filter: blur > 0 ? `blur(${blur}px)` : undefined,
                        transform: blur > 0 ? 'scale(1.05)' : undefined,
                    }}
                />
            </AnimatePresence>
        </div>
    );
}
