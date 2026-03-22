import { useRef, useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
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
                className="h-[calc(100vh-16rem)] overflow-y-auto rounded-lg bg-slate-950 p-4 font-mono text-sm text-green-400"
            >
                {messages.map((msg) => (
                    <div key={msg.id} className="whitespace-pre-wrap break-all">
                        {msg.text}
                    </div>
                ))}
            </div>

            {!autoScroll && (
                <button
                    type="button"
                    onClick={scrollToBottom}
                    className={clsx(
                        'absolute bottom-3 right-3',
                        'rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300',
                        'transition-colors hover:bg-slate-600',
                    )}
                >
                    {t('servers.console.scroll_to_bottom')}
                </button>
            )}
        </div>
    );
}
