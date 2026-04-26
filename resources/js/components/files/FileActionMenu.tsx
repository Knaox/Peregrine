import { useState, useEffect, useRef, useCallback, useLayoutEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { type FileActionMenuProps } from '@/components/files/FileActionMenu.props';
import { IconButton } from '@/components/ui/IconButton';

export function FileActionMenu({
    name: _name,
    isFile,
    isArchive,
    onRename,
    onDelete,
    onCompress,
    onDecompress,
    onChmod,
    onDownload,
    canUpdate = true,
    canDelete = true,
    canArchive = true,
    canDownload = true,
}: FileActionMenuProps) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    // The menu used to render with `absolute` inside the file row, but the
    // row's `overflow: hidden` (for its rounded corners) clipped the dropdown
    // when it overflowed. We now render via a portal anchored to <body> with
    // `position: fixed` coords computed from the trigger wrapper — escapes
    // every parent overflow / transform context.
    const triggerRef = useRef<HTMLSpanElement | null>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const [coords, setCoords] = useState<{ top: number; right: number } | null>(null);

    const computeCoords = useCallback(() => {
        const btn = triggerRef.current;
        if (!btn) return;
        const rect = btn.getBoundingClientRect();
        setCoords({
            top: rect.bottom + 4,
            right: Math.max(8, window.innerWidth - rect.right),
        });
    }, []);

    useLayoutEffect(() => {
        if (isOpen) computeCoords();
    }, [isOpen, computeCoords]);

    useEffect(() => {
        if (!isOpen) return;
        const onScrollOrResize = () => computeCoords();
        window.addEventListener('scroll', onScrollOrResize, true);
        window.addEventListener('resize', onScrollOrResize);
        return () => {
            window.removeEventListener('scroll', onScrollOrResize, true);
            window.removeEventListener('resize', onScrollOrResize);
        };
    }, [isOpen, computeCoords]);

    const handleClose = useCallback((e: MouseEvent) => {
        if (menuRef.current && menuRef.current.contains(e.target as Node)) return;
        if (triggerRef.current && triggerRef.current.contains(e.target as Node)) return;
        setIsOpen(false);
    }, []);

    useEffect(() => {
        if (isOpen) {
            document.addEventListener('click', handleClose, true);
            return () => document.removeEventListener('click', handleClose, true);
        }
    }, [isOpen, handleClose]);

    const handleAction = (action?: () => void) => {
        setIsOpen(false);
        if (action) action();
    };

    const showDownload = isFile && canDownload && !!onDownload;
    const showDivider = canDelete && (canUpdate || canArchive || showDownload);
    const hasAnyItem = canUpdate || canDelete || canArchive || showDownload;

    const dropdown = isOpen && hasAnyItem && coords
        ? createPortal(
            <div
                ref={menuRef}
                style={{ position: 'fixed', top: coords.top, right: coords.right, zIndex: 1000 }}
                className="w-44 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl py-1"
            >
                {canUpdate && (
                    <button
                        type="button"
                        onClick={() => handleAction(onRename)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors"
                    >
                        <svg className="w-4 h-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {t('servers.files.rename')}
                    </button>
                )}

                {showDownload && (
                    <button
                        type="button"
                        onClick={() => handleAction(onDownload)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors"
                    >
                        <svg className="w-4 h-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {t('servers.files.download', { defaultValue: 'Download' })}
                    </button>
                )}

                {canArchive && !isArchive && (
                    <button
                        type="button"
                        onClick={() => handleAction(onCompress)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors"
                    >
                        <svg className="w-4 h-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {t('servers.files.compress')}
                    </button>
                )}

                {canArchive && isArchive && (
                    <button
                        type="button"
                        onClick={() => handleAction(onDecompress)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors"
                    >
                        <svg className="w-4 h-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {t('servers.files.decompress')}
                    </button>
                )}

                {canUpdate && (
                    <button
                        type="button"
                        onClick={() => handleAction(onChmod)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] transition-colors"
                    >
                        <svg className="w-4 h-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        {t('servers.files.chmod')}
                    </button>
                )}

                {showDivider && (
                    <div className="border-t border-[var(--color-border)] my-1" />
                )}

                {canDelete && (
                    <button
                        type="button"
                        onClick={() => handleAction(onDelete)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {t('servers.files.delete')}
                    </button>
                )}
            </div>,
            document.body,
        )
        : null;

    return (
        <>
            <span ref={triggerRef} className="inline-flex">
                <IconButton
                    icon={
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                    }
                    size="sm"
                    onClick={() => setIsOpen((prev) => !prev)}
                />
            </span>
            {dropdown}
        </>
    );
}
