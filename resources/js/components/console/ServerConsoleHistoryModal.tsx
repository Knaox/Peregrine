import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { colorize } from '@/components/console/ConsoleOutput';
import type { ConsoleMessage } from '@/types/ConsoleMessage';
import { useNamespace } from '@/i18n/useNamespace';

const MAX_LINES = 1000;
const PRESETS = [100, 500, 1000] as const;

interface ServerConsoleHistoryModalProps {
    open: boolean;
    onClose: () => void;
    history: ConsoleMessage[];
}

const HistoryIcon = (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
);

/**
 * Read-only viewer for the rolling 1000-line console history, filterable by how
 * many of the most-recent lines to show. Reuses the live console's colourising
 * so it reads identically — just bounded to fit inside the modal.
 */
export function ServerConsoleHistoryModal({ open, onClose, history }: ServerConsoleHistoryModalProps) {
    useNamespace(['server-console'] as const);
    const { t } = useTranslation('server-console');
    const [limit, setLimit] = useState<number>(MAX_LINES);
    const bodyRef = useRef<HTMLDivElement>(null);

    const shown = useMemo(() => {
        const n = Math.max(1, Math.min(MAX_LINES, Number.isFinite(limit) && limit > 0 ? limit : MAX_LINES));
        return history.slice(-n);
    }, [history, limit]);

    // Jump to the most recent line whenever the view opens or the filter changes.
    useEffect(() => {
        if (!open) return;
        const el = bodyRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [open, limit, shown.length]);

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={t('history.title')}
            icon={HistoryIcon}
            size="lg"
            footer={<Button variant="ghost" onClick={onClose}>{t('history.close')}</Button>}
        >
            {/* Filter row */}
            <div className="flex flex-wrap items-center gap-2 pb-2">
                <label className="flex items-center gap-2 text-[var(--color-text-secondary)]">
                    {t('history.show_last')}
                    <input
                        type="number"
                        min={1}
                        max={MAX_LINES}
                        value={limit}
                        onChange={(e) => setLimit(Math.max(1, Math.min(MAX_LINES, Number(e.target.value))))}
                        className="w-20 rounded-[var(--radius-sm)] border border-[var(--color-border)] bg-[var(--color-surface)] px-2 py-1 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none"
                    />
                    {t('history.lines')}
                </label>
                <div className="ml-auto flex items-center gap-1">
                    {PRESETS.map((p) => (
                        <button
                            key={p}
                            type="button"
                            onClick={() => setLimit(p)}
                            className={clsx(
                                'cursor-pointer rounded-[var(--radius-sm)] px-2 py-1 text-xs font-medium transition-colors',
                                limit === p
                                    ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)]'
                                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                            )}
                        >
                            {p}
                        </button>
                    ))}
                </div>
            </div>

            <div className="pb-2 text-[11px] text-[var(--color-text-muted)]">
                {t('history.count', { shown: shown.length, total: history.length })}
            </div>

            {/* Terminal-style body */}
            <div className="overflow-hidden rounded-[var(--radius)] border" style={{ borderColor: 'rgba(255,255,255,0.08)' }}>
                <div
                    ref={bodyRef}
                    className="terminal-scrollbar max-h-[50vh] overflow-y-auto p-3"
                    style={{
                        background: '#0d1117',
                        fontFamily: 'var(--font-mono)',
                        fontSize: 'clamp(11px, 2.5vw, 13px)',
                        lineHeight: 1.7,
                    }}
                >
                    {shown.length === 0 ? (
                        <div className="py-8 text-center text-sm text-[var(--color-text-muted)]">{t('history.empty')}</div>
                    ) : (
                        shown.map((msg, i) => {
                            const { color, bold } = colorize(msg.text);
                            return (
                                <div key={msg.id} className="flex gap-3">
                                    <span className="hidden w-8 flex-shrink-0 select-none pt-px text-right text-[10px] opacity-30 sm:block" style={{ color: 'var(--color-text-muted)' }}>
                                        {i + 1}
                                    </span>
                                    <span className="min-w-0 whitespace-pre-wrap break-all" style={{ color, fontWeight: bold ? 600 : 400 }}>
                                        {msg.text}
                                    </span>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </Modal>
    );
}
