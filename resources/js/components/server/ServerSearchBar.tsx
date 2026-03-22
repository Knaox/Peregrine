import { useTranslation } from 'react-i18next';
import type { ServerSearchBarProps } from '@/components/server/ServerSearchBar.props';

const SearchIcon = (
    <svg
        className="h-5 w-5 text-slate-400"
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
);

const ClearIcon = (
    <svg
        className="h-4 w-4 text-slate-400 hover:text-white transition-colors"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        strokeWidth={2}
    >
        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
);

export function ServerSearchBar({ value, onChange }: ServerSearchBarProps) {
    const { t } = useTranslation();

    return (
        <div className="relative">
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                {SearchIcon}
            </div>
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={t('servers.list.search')}
                className="w-full rounded-lg border border-slate-700 bg-slate-800 py-2.5 pl-10 pr-10 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
            />
            {value.length > 0 && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    className="absolute inset-y-0 right-0 flex items-center pr-3"
                >
                    {ClearIcon}
                </button>
            )}
        </div>
    );
}
