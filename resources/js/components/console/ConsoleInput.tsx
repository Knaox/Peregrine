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
                'flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <span className="font-mono text-sm text-slate-500">$</span>
            <input
                type="text"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={disabled}
                placeholder={t('servers.console.send_command')}
                autoFocus
                className={clsx(
                    'flex-1 bg-transparent font-mono text-sm text-slate-200',
                    'outline-none placeholder:text-slate-500',
                    disabled && 'cursor-not-allowed',
                )}
            />
        </div>
    );
}
