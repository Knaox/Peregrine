import { useState, useCallback, useRef, useEffect } from 'react';

interface PointerDragConfig {
    onDragEnd: (itemId: string, sourceZoneId: string, targetZoneId: string, insertIndex: number) => void;
    onDragCancel?: () => void;
}

interface DragHandleProps {
    onPointerDown: (e: React.PointerEvent) => void;
    style: React.CSSProperties;
    'data-drag-id': string;
    'data-drag-zone': string;
}

type DropZoneRef = (el: HTMLElement | null) => void;

interface UsePointerDragReturn {
    getDragHandleProps: (itemId: string, zoneId: string) => DragHandleProps;
    getDropZoneRef: (zoneId: string) => DropZoneRef;
    isDragging: boolean;
    draggedItemId: string | null;
    activeDropZoneId: string | null;
    insertIndex: number;
}

const DEAD_ZONE = 5;
const LONG_PRESS_MS = 300;
const SCROLL_EDGE = 60;
const SCROLL_SPEED = 12;
const DRAG_CLASS = 'peregrine-dragging-source';
const BODY_DRAG_CLASS = 'peregrine-dragging';

interface DragState {
    itemId: string;
    zoneId: string;
    startX: number;
    startY: number;
    pointerType: string;
    activated: boolean;
    longPressReady: boolean;
}

function createGhost(rect: DOMRect): HTMLDivElement {
    const g = document.createElement('div');
    Object.assign(g.style, {
        position: 'fixed', left: '0', top: '0', width: `${rect.width}px`, height: `${rect.height}px`,
        background: 'var(--color-surface)', border: '1px solid var(--color-primary)',
        opacity: '0.92', borderRadius: 'var(--radius-lg)', pointerEvents: 'none',
        zIndex: '9999', boxShadow: '0 18px 50px rgba(0,0,0,0.55)',
        transition: 'none', willChange: 'transform', transformOrigin: 'center center',
    });
    document.body.appendChild(g);
    return g;
}

// Position the ghost with a GPU-composited transform (no layout/reflow) so it
// tracks the pointer smoothly at 60fps. A tiny scale gives a "lifted" feel.
function moveGhost(g: HTMLDivElement, cx: number, cy: number): void {
    const w = parseFloat(g.style.width);
    const h = parseFloat(g.style.height);
    g.style.transform = `translate3d(${cx - w / 2}px, ${cy - h / 2}px, 0) scale(1.03)`;
}

// Hard block for the browser's native text-selection / image-drag gestures.
// Attached while a handle is pressed so nothing can be highlighted — covers the
// window *before* the drag threshold is crossed, in every browser.
function blockSelectionEvent(e: Event): void {
    e.preventDefault();
}

function dist(dx: number, dy: number): number {
    return Math.sqrt(dx * dx + dy * dy);
}

export function usePointerDrag(config: PointerDragConfig): UsePointerDragReturn {
    const [isDragging, setIsDragging] = useState(false);
    const [draggedItemId, setDraggedItemId] = useState<string | null>(null);
    const [activeDropZoneId, setActiveDropZoneId] = useState<string | null>(null);
    const [insertIndex, setInsertIndex] = useState(-1);

    const stateRef = useRef<DragState | null>(null);
    const ghostRef = useRef<HTMLDivElement | null>(null);
    const sourceRef = useRef<HTMLElement | null>(null);
    const zonesRef = useRef(new Map<string, HTMLElement>());
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const rafRef = useRef<number | null>(null);
    const configRef = useRef(config);
    const zoneIdRef = useRef<string | null>(null);
    const idxRef = useRef(-1);
    configRef.current = config;

    useEffect(() => { zoneIdRef.current = activeDropZoneId; }, [activeDropZoneId]);
    useEffect(() => { idxRef.current = insertIndex; }, [insertIndex]);

    const cleanup = useCallback(() => {
        document.body.classList.remove(BODY_DRAG_CLASS);
        ghostRef.current?.remove();
        ghostRef.current = null;
        sourceRef.current?.classList.remove(DRAG_CLASS);
        sourceRef.current = null;
        if (timerRef.current) { clearTimeout(timerRef.current); timerRef.current = null; }
        if (rafRef.current) { cancelAnimationFrame(rafRef.current); rafRef.current = null; }
        stateRef.current = null;
        setIsDragging(false);
        setDraggedItemId(null);
        setActiveDropZoneId(null);
        setInsertIndex(-1);
    }, []);

    const activate = useCallback((s: DragState, cx: number, cy: number) => {
        s.activated = true;
        const el = document.querySelector<HTMLElement>(
            `[data-drag-id="${s.itemId}"][data-drag-zone="${s.zoneId}"]`,
        );
        if (!el) return;
        sourceRef.current = el;
        el.classList.add(DRAG_CLASS);
        const rect = el.getBoundingClientRect();
        const ghost = createGhost(rect);
        moveGhost(ghost, cx, cy);
        ghostRef.current = ghost;
        setIsDragging(true);
        setDraggedItemId(s.itemId);
    }, []);

    const findZone = useCallback((cx: number, cy: number): string | null => {
        for (const [id, el] of zonesRef.current) {
            const r = el.getBoundingClientRect();
            if (cx >= r.left && cx <= r.right && cy >= r.top && cy <= r.bottom) return id;
        }
        return null;
    }, []);

    const computeIdx = useCallback((zone: HTMLElement, cy: number, dragId: string): number => {
        const kids = Array.from(zone.querySelectorAll<HTMLElement>('[data-drag-id]'))
            .filter((c) => c.getAttribute('data-drag-id') !== dragId);
        for (let i = 0; i < kids.length; i++) {
            const c = kids[i];
            if (!c) continue;
            const r = c.getBoundingClientRect();
            if (cy < r.top + r.height / 2) return i;
        }
        return kids.length;
    }, []);

    const autoScroll = useCallback((cy: number) => {
        if (rafRef.current) { cancelAnimationFrame(rafRef.current); rafRef.current = null; }
        const fromTop = cy;
        const fromBottom = window.innerHeight - cy;
        let delta = 0;
        if (fromTop < SCROLL_EDGE) delta = -SCROLL_SPEED * (1 - fromTop / SCROLL_EDGE);
        else if (fromBottom < SCROLL_EDGE) delta = SCROLL_SPEED * (1 - fromBottom / SCROLL_EDGE);
        if (delta !== 0) {
            const tick = () => { window.scrollBy(0, delta); rafRef.current = requestAnimationFrame(tick); };
            rafRef.current = requestAnimationFrame(tick);
        }
    }, []);

    const onMove = useCallback((e: PointerEvent) => {
        const s = stateRef.current;
        if (!s) return;
        const dx = e.clientX - s.startX;
        const dy = e.clientY - s.startY;
        const d = dist(dx, dy);

        if (!s.activated) {
            if (s.pointerType === 'touch') {
                if (!s.longPressReady && d > 10) { cleanup(); return; }
                if (s.longPressReady && d > DEAD_ZONE) activate(s, e.clientX, e.clientY);
                return;
            }
            if (d > DEAD_ZONE) activate(s, e.clientX, e.clientY);
            return;
        }

        e.preventDefault();
        if (ghostRef.current) moveGhost(ghostRef.current, e.clientX, e.clientY);

        const zid = findZone(e.clientX, e.clientY);
        setActiveDropZoneId(zid);
        if (zid) {
            const zoneEl = zonesRef.current.get(zid);
            if (zoneEl) setInsertIndex(computeIdx(zoneEl, e.clientY, s.itemId));
        } else {
            setInsertIndex(-1);
        }
        autoScroll(e.clientY);
    }, [cleanup, activate, findZone, computeIdx, autoScroll]);

    const removeListeners = useRef(() => {});

    const onUp = useCallback(() => {
        const s = stateRef.current;
        if (!s || !s.activated) {
            cleanup();
            if (s && !s.activated) configRef.current.onDragCancel?.();
            removeListeners.current();
            return;
        }
        const zid = zoneIdRef.current;
        const idx = idxRef.current;
        if (zid && idx >= 0) {
            configRef.current.onDragEnd(s.itemId, s.zoneId, zid, idx);
        } else {
            configRef.current.onDragCancel?.();
        }
        cleanup();
        removeListeners.current();
    }, [cleanup]);

    const onCancel = useCallback(() => {
        cleanup();
        configRef.current.onDragCancel?.();
        removeListeners.current();
    }, [cleanup]);

    // Stable refs so document listeners always call latest logic
    const moveRef = useRef(onMove);
    const upRef = useRef(onUp);
    const cancelRef = useRef(onCancel);
    moveRef.current = onMove;
    upRef.current = onUp;
    cancelRef.current = onCancel;

    const stableMove = useCallback((e: PointerEvent) => moveRef.current(e), []);
    const stableUp = useCallback(() => upRef.current(), []);
    const stableCancel = useCallback(() => cancelRef.current(), []);

    removeListeners.current = () => {
        document.removeEventListener('pointermove', stableMove);
        document.removeEventListener('pointerup', stableUp);
        document.removeEventListener('pointercancel', stableCancel);
        document.removeEventListener('selectstart', blockSelectionEvent);
        document.removeEventListener('dragstart', blockSelectionEvent);
    };

    useEffect(() => {
        return () => { cleanup(); removeListeners.current(); };
    }, [cleanup]);

    const onDown = useCallback((e: React.PointerEvent, itemId: string, zoneId: string) => {
        if (stateRef.current) return;
        const s: DragState = {
            itemId, zoneId, startX: e.clientX, startY: e.clientY,
            pointerType: e.pointerType, activated: false,
            longPressReady: e.pointerType !== 'touch',
        };
        stateRef.current = s;
        if (e.pointerType === 'touch') {
            timerRef.current = setTimeout(() => {
                if (stateRef.current === s) s.longPressReady = true;
                timerRef.current = null;
            }, LONG_PRESS_MS);
        }
        // Lock text selection for the ENTIRE press — from the very first pixel,
        // before the drag threshold is reached and during the touch long-press
        // hold. Three layers so no browser slips through: clear any active
        // range, CSS user-select via the body class, and a selectstart guard.
        window.getSelection?.()?.removeAllRanges();
        document.body.classList.add(BODY_DRAG_CLASS);
        document.addEventListener('selectstart', blockSelectionEvent);
        document.addEventListener('dragstart', blockSelectionEvent);
        document.addEventListener('pointermove', stableMove);
        document.addEventListener('pointerup', stableUp);
        document.addEventListener('pointercancel', stableCancel);
    }, [stableMove, stableUp, stableCancel]);

    const getDragHandleProps = useCallback((itemId: string, zoneId: string): DragHandleProps => ({
        onPointerDown: (e: React.PointerEvent) => onDown(e, itemId, zoneId),
        style: { touchAction: 'none', cursor: 'grab', userSelect: 'none', WebkitUserSelect: 'none' },
        'data-drag-id': itemId,
        'data-drag-zone': zoneId,
    }), [onDown]);

    const getDropZoneRef = useCallback((zoneId: string): DropZoneRef => (el) => {
        if (el) zonesRef.current.set(zoneId, el);
        else zonesRef.current.delete(zoneId);
    }, []);

    return { getDragHandleProps, getDropZoneRef, isDragging, draggedItemId, activeDropZoneId, insertIndex };
}
