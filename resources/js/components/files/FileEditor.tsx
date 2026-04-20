import { useCallback, useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { type FileEditorProps } from '@/components/files/FileEditor.props';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';

function extractFileName(filePath: string): string {
    const parts = filePath.split('/');
    return parts[parts.length - 1] || filePath;
}

/**
 * Full-screen code editor with line numbers, Ctrl+S shortcut,
 * and proper monospace styling. Replaces the old side-panel textarea.
 */
export function FileEditor({
    filePath, content, isDirty, isSaving,
    onContentChange, onSave, onClose,
    canEdit = true,
}: FileEditorProps) {
    const { t } = useTranslation();
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    /* Keyboard shortcuts */
    const handleKeyDown = useCallback((e: KeyboardEvent) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 's') {
            e.preventDefault();
            if (canEdit && isDirty && !isSaving) onSave();
        }
        if (e.key === 'Escape') onClose();
    }, [canEdit, isDirty, isSaving, onSave, onClose]);

    useEffect(() => {
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [handleKeyDown]);

    /* Auto-focus textarea */
    useEffect(() => { textareaRef.current?.focus(); }, []);

    /* Line numbers */
    const lineCount = useMemo(() => content.split('\n').length, [content]);

    const lineNumbers = useMemo(() => {
        const lines: string[] = [];
        for (let i = 1; i <= lineCount; i++) lines.push(String(i));
        return lines;
    }, [lineCount]);

    /* Sync scroll between line numbers and textarea */
    const lineNumRef = useRef<HTMLDivElement>(null);
    const handleScroll = useCallback(() => {
        if (textareaRef.current && lineNumRef.current) {
            lineNumRef.current.scrollTop = textareaRef.current.scrollTop;
        }
    }, []);

    const fileName = extractFileName(filePath);
    const ext = fileName.split('.').pop()?.toLowerCase() ?? '';
    const langLabel = ext === 'yml' || ext === 'yaml' ? 'YAML' : ext === 'json' ? 'JSON' : ext === 'properties' ? 'PROPS' : ext.toUpperCase();

    return (
        <>
            {/* Backdrop */}
            <m.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="fixed inset-0 bg-black/70 backdrop-blur-sm z-40"
                onClick={onClose}
                role="presentation"
            />

            {/* Editor panel — full screen with glass effect */}
            <m.div
                initial={{ opacity: 0, scale: 0.97, y: 20 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.97, y: 20 }}
                transition={{ duration: 0.25, ease: [0.4, 0, 0.2, 1] }}
                className="fixed inset-0 sm:inset-4 md:inset-8 lg:inset-12 z-50 flex flex-col sm:rounded-[var(--radius-xl)] overflow-hidden"
                style={{
                    background: 'var(--color-background)',
                    border: '1px solid var(--color-border)',
                    boxShadow: '0 25px 60px rgba(0,0,0,0.6)',
                }}
            >
                {/* Header bar */}
                <div className="flex items-center justify-between px-3 sm:px-4 py-2.5 border-b border-[var(--color-border)]"
                    style={{ background: 'var(--color-surface)' }}>
                    <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                        <svg className="h-4 w-4 flex-shrink-0 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span className="text-sm font-medium text-[var(--color-text-primary)] truncate" title={filePath}>
                            {fileName}
                        </span>
                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded"
                            style={{ background: 'rgba(var(--color-accent-rgb), 0.1)', color: 'var(--color-accent)' }}>
                            {langLabel}
                        </span>
                        {isDirty && canEdit && <Badge color="orange">{t('servers.files.modified_badge')}</Badge>}
                        {!canEdit && (
                            <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded"
                                style={{ background: 'rgba(var(--color-text-muted-rgb, 100 116 139), 0.15)', color: 'var(--color-text-secondary)' }}>
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                {t('servers.files.read_only')}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {canEdit && (
                            <>
                                <span className="hidden sm:block text-[10px] text-[var(--color-text-muted)] font-mono">
                                    {isDirty ? 'Ctrl+S' : ''}
                                </span>
                                <Button variant="primary" size="sm" disabled={!isDirty} isLoading={isSaving} onClick={onSave}>
                                    {t('servers.files.save')}
                                </Button>
                            </>
                        )}
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            {t('servers.files.close')}
                        </Button>
                    </div>
                </div>

                {/* Editor body — line numbers + textarea */}
                <div className="flex flex-1 overflow-hidden" style={{ background: 'var(--color-background)' }}>
                    {/* Line numbers gutter */}
                    <div
                        ref={lineNumRef}
                        className="hidden sm:block flex-shrink-0 overflow-hidden select-none text-right pr-3 pl-4 pt-4 pb-4"
                        style={{
                            fontFamily: 'var(--font-mono)',
                            fontSize: 13,
                            lineHeight: '1.65',
                            color: 'var(--color-text-muted)',
                            borderRight: '1px solid var(--color-border)',
                            background: 'var(--color-surface)',
                            minWidth: 52,
                        }}
                    >
                        {lineNumbers.map((n) => (
                            <div key={n}>{n}</div>
                        ))}
                    </div>

                    {/* Textarea */}
                    <textarea
                        ref={textareaRef}
                        value={content}
                        onChange={(e) => onContentChange(e.target.value)}
                        onScroll={handleScroll}
                        readOnly={!canEdit}
                        className={clsx(
                            'flex-1 p-2 sm:p-4 resize-none focus:outline-none',
                            'text-[var(--color-text-primary)] bg-transparent',
                            'selection:bg-[var(--color-primary)]/20',
                        )}
                        style={{
                            fontFamily: 'var(--font-mono)',
                            fontSize: 'clamp(11px, 2.5vw, 13px)',
                            lineHeight: '1.65',
                            tabSize: 4,
                        }}
                        spellCheck={false}
                        autoCapitalize="off"
                        autoCorrect="off"
                    />
                </div>

                {/* Status bar */}
                <div className="flex items-center justify-between px-3 sm:px-4 py-1.5 text-[10px] font-mono border-t border-[var(--color-border)]"
                    style={{ background: 'var(--color-surface)', color: 'var(--color-text-muted)' }}>
                    <span className="truncate mr-2">{filePath}</span>
                    <span>{lineCount} {lineCount === 1 ? 'line' : 'lines'}</span>
                </div>
            </m.div>
        </>
    );
}
