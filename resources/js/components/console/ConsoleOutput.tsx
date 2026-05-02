import { useRef, useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import type { ConsoleOutputProps } from '@/components/console/ConsoleOutput.props';

function colorize(text: string): { color: string; bold: boolean } {
    if (text.startsWith('[Peregrine]')) return { color: 'var(--color-primary)', bold: true };
    if (/\b(error|exception|fatal|fail)/i.test(text)) return { color: 'var(--color-danger)', bold: false };
    if (/\b(warn|warning)/i.test(text)) return { color: 'var(--color-warning)', bold: false };
    if (/\b(info|done|ready|loaded|started)/i.test(text)) return { color: 'var(--color-info)', bold: false };
    return { color: 'var(--color-success)', bold: false };
}

export function ConsoleOutput({ messages }: ConsoleOutputProps) {
    const { t } = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const [autoScroll, setAutoScroll] = useState(true);

    const handleScroll = useCallback(() => {
        const el = containerRef.current;
        if (!el) return;
        setAutoScroll(el.scrollTop + el.clientHeight >= el.scrollHeight - 50);
    }, []);

    useEffect(() => {
        if (!autoScroll) return;
        const el = containerRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages, autoScroll]);

    const scrollToBottom = useCallback(() => {
        const el = containerRef.current;
        if (el) { el.scrollTop = el.scrollHeight; setAutoScroll(true); }
    }, []);

    return (
        // Bounded height : the parent <main> in ServerDetailPage uses
        // overflow-y-auto, so a flex-1 here would grow with the message
        // list (infinite vertical expansion). We pin the terminal to a
        // viewport-relative height with min/max guards so the inner body
        // owns the scroll. min-h-[400px] keeps it usable on small phones,
        // max-h-[1100px] prevents it from dominating ultra-wide monitors.
        // 78dvh keeps a comfortable strip for the input + tab dock + the
        // status header above without making the page feel cramped.
        <div className="relative flex flex-col rounded-[var(--radius-lg)] overflow-hidden h-[78dvh] min-h-[400px] max-h-[1100px]"
            style={{ border: '1px solid var(--color-border)', boxShadow: 'var(--shadow-inset)' }}>

            {/* Terminal header bar — hardcoded dark so the terminal stays
                readable in light theme (same convention as iTerm / VSCode
                terminal which keep a dark scheme regardless of app theme). */}
            <div className="flex items-center gap-2 px-3 sm:px-4 py-2 border-b flex-shrink-0"
                style={{ background: '#161b22', borderColor: 'rgba(255,255,255,0.08)' }}>
                <div className="hidden sm:flex items-center gap-1.5">
                    <div className="h-3 w-3 rounded-full" style={{ background: '#ef4444', opacity: 0.8 }} />
                    <div className="h-3 w-3 rounded-full" style={{ background: '#f59e0b', opacity: 0.8 }} />
                    <div className="h-3 w-3 rounded-full" style={{ background: '#10b981', opacity: 0.8 }} />
                </div>
                <span className="flex-1 text-center text-[10px] font-mono" style={{ color: 'rgba(255,255,255,0.5)' }}>
                    {t('servers.console.title')}
                </span>
                <span className="text-[10px] font-mono px-1.5 py-0.5 rounded"
                    style={{ background: 'rgba(16, 185, 129, 0.15)', color: '#34d399' }}>
                    {messages.length}
                </span>
            </div>

            {/* Terminal body — scroll-region. Background hardcoded dark so the
                semantic log colours (success / warning / danger / info)
                stay legible regardless of app theme. */}
            <div
                ref={containerRef}
                onScroll={handleScroll}
                className="terminal-scrollbar flex-1 min-h-0 overflow-y-auto p-2 sm:p-4"
                style={{
                    background: '#0d1117',
                    fontFamily: 'var(--font-mono)',
                    fontSize: 'clamp(11px, 2.5vw, 13px)',
                    lineHeight: 1.7,
                    WebkitOverflowScrolling: 'touch',
                    overscrollBehavior: 'contain',
                }}
            >
                {messages.map((msg, i) => {
                    const { color, bold } = colorize(msg.text);
                    return (
                        <div key={msg.id} className="flex gap-1.5 sm:gap-3 group hover:bg-white/[0.02] rounded px-1 -mx-1"
                            style={{ transition: 'background 100ms ease' }}>
                            <span className="select-none text-[var(--color-text-muted)] opacity-30 flex-shrink-0 w-6 sm:w-8 text-right text-[10px] sm:text-[11px] pt-px hidden sm:block">
                                {i + 1}
                            </span>
                            <span className="whitespace-pre-wrap break-all min-w-0"
                                style={{ color, fontWeight: bold ? 600 : 400 }}>
                                {msg.text}
                            </span>
                        </div>
                    );
                })}
                {messages.length === 0 && (
                    <div className="flex items-center justify-center h-32 text-[var(--color-text-muted)] text-sm">
                        <span className="animate-pulse">_</span>
                    </div>
                )}
            </div>

            {/* Scroll to bottom button */}
            <AnimatePresence>
                {!autoScroll && (
                    <m.button
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 8 }}
                        transition={{ duration: 0.15 }}
                        type="button"
                        onClick={scrollToBottom}
                        className={clsx(
                            'absolute bottom-3 right-3 cursor-pointer',
                            'rounded-[var(--radius-full)] glass-card-enhanced',
                            'px-3 py-2 sm:py-1.5 text-xs font-medium text-[var(--color-text-secondary)]',
                            'hover:text-[var(--color-text-primary)]',
                            'min-h-[44px] sm:min-h-0 flex items-center',
                        )}
                    >
                        <svg className="inline-block h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        {t('servers.console.scroll_to_bottom')}
                    </m.button>
                )}
            </AnimatePresence>
        </div>
    );
}
