import { type FileBreadcrumbProps } from '@/components/files/FileBreadcrumb.props';

export function FileBreadcrumb({ currentDirectory, onNavigate }: FileBreadcrumbProps) {
    const segments = currentDirectory.split('/').filter(Boolean);

    const buildPath = (index: number): string => {
        return '/' + segments.slice(0, index + 1).join('/');
    };

    return (
        <nav className="flex items-center gap-1 text-sm text-[var(--color-text-secondary)]">
            <button
                type="button"
                onClick={() => onNavigate('/')}
                className="hover:text-white transition-colors p-1"
            >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
                </svg>
            </button>

            {segments.map((segment, index) => (
                <span key={buildPath(index)} className="flex items-center gap-1">
                    <span className="text-[var(--color-text-muted)]">/</span>
                    <button
                        type="button"
                        onClick={() => onNavigate(buildPath(index))}
                        className="hover:text-white transition-colors px-1"
                    >
                        {segment}
                    </button>
                </span>
            ))}
        </nav>
    );
}
