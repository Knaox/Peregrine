import { useState, useCallback, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { ConsoleInputProps } from '@/components/console/ConsoleInput.props';

export function ConsoleInput({ onSend, onHistoryUp, onHistoryDown, disabled }: ConsoleInputProps) {
    const { t } = useTranslation();
    const [value, setValue] = useState('');

    const handleKeyDown = useCallback(
        (e: KeyboardEvent<HTMLInputElement>) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const trimmed = value.trim();
                if (!trimmed) return;
                onSend(trimmed);
                setValue('');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const cmd = onHistoryUp();
                setValue(cmd);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                const cmd = onHistoryDown();
                setValue(cmd);
            }
        },
        [value, onSend, onHistoryUp, onHistoryDown],
    );

    return (
        <div
            className={clsx(
                'flex items-center gap-2 px-3 py-2',
                'bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius)]',
                'transition-all duration-[var(--transition-base)]',
                'focus-within:border-[var(--color-primary)] focus-within:ring-1 focus-within:ring-[var(--color-primary-glow)]',
                disabled && 'opacity-40 cursor-not-allowed',
            )}
        >
            <span className="text-[var(--color-primary)] font-[var(--font-mono)] text-sm font-bold">$</span>
            <input
                type="text"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={disabled}
                placeholder={t('servers.console.send_command')}
                autoFocus
                className={clsx(
                    'flex-1 bg-transparent font-[var(--font-mono)] text-sm',
                    'text-[var(--color-text-primary)]',
                    'outline-none placeholder:text-[var(--color-text-muted)]',
                    disabled && 'cursor-not-allowed',
                )}
            />
        </div>
    );
}
