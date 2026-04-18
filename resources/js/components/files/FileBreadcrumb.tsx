import clsx from 'clsx';
import { type FileBreadcrumbProps } from '@/components/files/FileBreadcrumb.props';

export function FileBreadcrumb({ currentDirectory, onNavigate }: FileBreadcrumbProps) {
    const segments = currentDirectory.split('/').filter(Boolean);

    const buildPath = (index: number): string => {
        return '/' + segments.slice(0, index + 1).join('/');
    };

    return (
        <nav className="flex items-center gap-0.5 text-sm overflow-x-auto py-1">
            {/* Root / Home */}
            <button
                type="button"
                onClick={() => onNavigate('/')}
                className={clsx(
                    'flex items-center gap-1.5 px-2.5 py-1.5 rounded-[var(--radius)] cursor-pointer',
                    'transition-all duration-150',
                    'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)]',
                )}
            >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
                </svg>
                <span className="hidden sm:inline">/</span>
            </button>

            {segments.map((segment, index) => {
                const isLast = index === segments.length - 1;
                return (
                    <span key={buildPath(index)} className="flex items-center gap-0.5">
                        <svg className="w-3.5 h-3.5 text-[var(--color-text-muted)] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                        <button
                            type="button"
                            onClick={() => onNavigate(buildPath(index))}
                            className={clsx(
                                'px-2 py-1 rounded-[var(--radius-sm)] cursor-pointer transition-all duration-150 text-sm whitespace-nowrap',
                                isLast
                                    ? 'text-[var(--color-text-primary)] font-medium'
                                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)]',
                            )}
                        >
                            {segment}
                        </button>
                    </span>
                );
            })}
        </nav>
    );
}
