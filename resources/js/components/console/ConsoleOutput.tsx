import { useRef, useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import type { ConsoleOutputProps } from '@/components/console/ConsoleOutput.props';

export function ConsoleOutput({ messages }: ConsoleOutputProps) {
    const { t } = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const [autoScroll, setAutoScroll] = useState(true);

    const handleScroll = useCallback(() => {
        const el = containerRef.current;
        if (!el) return;
        const isNearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 50;
        setAutoScroll(isNearBottom);
    }, []);

    useEffect(() => {
        if (!autoScroll) return;
        const el = containerRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [messages, autoScroll]);

    const scrollToBottom = useCallback(() => {
        const el = containerRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
            setAutoScroll(true);
        }
    }, []);

    return (
        <div className="relative flex-1">
            <div
                ref={containerRef}
                onScroll={handleScroll}
                className={clsx(
                    'h-[calc(100vh-16rem)] overflow-y-auto p-4',
                    'bg-[var(--color-background)] rounded-[var(--radius-lg)]',
                    'border border-[var(--color-border)]',
                    'font-[var(--font-mono)] text-sm text-green-400',
                )}
            >
                {messages.map((msg) => (
                    <div
                        key={msg.id}
                        className="whitespace-pre-wrap break-all transition-opacity duration-200"
                    >
                        {msg.text}
                    </div>
                ))}
            </div>

            <AnimatePresence>
                {!autoScroll && (
                    <m.button
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.9 }}
                        transition={{ duration: 0.15 }}
                        type="button"
                        onClick={scrollToBottom}
                        className={clsx(
                            'absolute bottom-3 right-3',
                            'rounded-[var(--radius-full)]',
                            'backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)]',
                            'px-3 py-1.5 text-xs font-medium text-[var(--color-text-secondary)]',
                            'transition-colors duration-[var(--transition-fast)]',
                            'hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
                        )}
                    >
                        {t('servers.console.scroll_to_bottom')}
                    </m.button>
                )}
            </AnimatePresence>
        </div>
    );
}
