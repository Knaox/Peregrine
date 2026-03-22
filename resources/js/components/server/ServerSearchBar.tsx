import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { ServerSearchBarProps } from '@/components/server/ServerSearchBar.props';

export function ServerSearchBar({ value, onChange }: ServerSearchBarProps) {
    const { t } = useTranslation();
    const [isFocused, setIsFocused] = useState(false);

    return (
        <div className="relative">
            {/* Search icon */}
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg
                    className={clsx(
                        'h-5 w-5 transition-colors duration-300',
                        isFocused ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-muted)]',
                    )}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"
                    />
                </svg>
            </div>

            {/* Input */}
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={() => setIsFocused(true)}
                onBlur={() => setIsFocused(false)}
                placeholder={t('servers.list.search')}
                className={clsx(
                    'w-full rounded-[var(--radius-lg)] py-2.5 pl-10 pr-10 text-sm',
                    'backdrop-blur-md bg-[var(--color-glass)] border',
                    'text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)]',
                    'transition-all duration-300 outline-none',
                    isFocused
                        ? 'border-[var(--color-primary)]/30 ring-1 ring-[var(--color-primary-glow)] pl-11'
                        : 'border-[var(--color-glass-border)]',
                )}
            />

            {/* Clear button */}
            <button
                type="button"
                onClick={() => onChange('')}
                className={clsx(
                    'absolute inset-y-0 right-0 flex items-center pr-3',
                    'transition-opacity duration-200',
                    value.length > 0 ? 'opacity-100' : 'opacity-0 pointer-events-none',
                )}
            >
                <svg
                    className={clsx(
                        'h-4 w-4 transition-all duration-200',
                        'text-[var(--color-text-muted)]',
                        'hover:text-[var(--color-text-primary)] hover:drop-shadow-[0_0_4px_var(--color-primary-glow)]',
                    )}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}
