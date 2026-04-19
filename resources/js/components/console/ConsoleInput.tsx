import { useState, useCallback, useRef, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { ConsoleInputProps } from '@/components/console/ConsoleInput.props';

export function ConsoleInput({ onSend, onHistoryUp, onHistoryDown, disabled }: ConsoleInputProps) {
    const { t } = useTranslation();
    const [value, setValue] = useState('');
    const [isFocused, setIsFocused] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

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
                setValue(onHistoryUp());
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setValue(onHistoryDown());
            }
        },
        [value, onSend, onHistoryUp, onHistoryDown],
    );

    return (
        <div
            className={clsx(
                'flex items-center gap-2 sm:gap-3 px-3 py-2.5 sm:px-4 sm:py-3 rounded-[var(--radius-lg)]',
                'transition-all duration-200',
                disabled && 'opacity-40 cursor-not-allowed',
            )}
            style={{
                background: 'var(--color-surface)',
                border: `1px solid ${isFocused ? 'var(--color-primary)' : 'var(--color-border)'}`,
                boxShadow: isFocused ? '0 0 16px var(--color-primary-glow), inset 0 1px 0 rgba(255,255,255,0.03)' : 'inset 0 1px 0 rgba(255,255,255,0.03)',
            }}
            onClick={() => inputRef.current?.focus()}
        >
            {/* Prompt symbol */}
            <span className="flex items-center gap-1.5 flex-shrink-0">
                <span className="text-[var(--color-primary)] font-mono text-sm font-bold">$</span>
                {isFocused && (
                    <span className="h-4 w-0.5 rounded-full animate-pulse" style={{ background: 'var(--color-primary)' }} />
                )}
            </span>

            <input
                ref={inputRef}
                type="text"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={handleKeyDown}
                onFocus={() => setIsFocused(true)}
                onBlur={() => setIsFocused(false)}
                disabled={disabled}
                placeholder={t('servers.console.send_command')}
                autoFocus
                className={clsx(
                    'flex-1 bg-transparent font-mono text-sm',
                    'text-[var(--color-text-primary)]',
                    'outline-none placeholder:text-[var(--color-text-muted)]',
                    disabled && 'cursor-not-allowed',
                )}
                style={{ fontFamily: 'var(--font-mono)' }}
            />

            {/* Send hint */}
            {value.trim().length > 0 && !disabled && (
                <span className="text-[10px] font-mono text-[var(--color-text-muted)] flex-shrink-0">
                    Enter
                </span>
            )}
        </div>
    );
}
