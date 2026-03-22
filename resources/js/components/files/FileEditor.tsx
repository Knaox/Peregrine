import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { type FileEditorProps } from '@/components/files/FileEditor.props';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';

function extractFileName(filePath: string): string {
    const parts = filePath.split('/');
    return parts[parts.length - 1] || filePath;
}

export function FileEditor({
    filePath,
    content,
    isDirty,
    isSaving,
    onContentChange,
    onSave,
    onClose,
}: FileEditorProps) {
    const { t } = useTranslation();

    return (
        <>
            {/* Overlay */}
            <div
                className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
                onClick={onClose}
                role="button"
                tabIndex={-1}
                aria-label={t('servers.files.close')}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') onClose();
                }}
            />

            {/* Panel */}
            <div
                className={clsx(
                    'fixed right-0 top-0 h-full w-2/3 max-w-3xl z-50',
                    'backdrop-blur-xl bg-[var(--color-surface)]/95 border-l border-[var(--color-border)]',
                    'flex flex-col shadow-[var(--shadow-lg)]',
                )}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-[var(--color-glass-border)]">
                    <div className="flex items-center gap-3 min-w-0">
                        <span
                            className="text-sm text-[var(--color-text-secondary)] truncate max-w-xs"
                            title={filePath}
                        >
                            {extractFileName(filePath)}
                        </span>
                        {isDirty && (
                            <Badge color="orange">
                                {t('servers.files.modified')}
                            </Badge>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            disabled={!isDirty}
                            isLoading={isSaving}
                            onClick={onSave}
                        >
                            {t('servers.files.save')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onClose}
                        >
                            {t('servers.files.close')}
                        </Button>
                    </div>
                </div>

                {/* Body */}
                <textarea
                    value={content}
                    onChange={(e) => onContentChange(e.target.value)}
                    className={clsx(
                        'flex-1 font-[var(--font-mono)] text-sm',
                        'bg-[var(--color-background)] text-[var(--color-text-primary)]',
                        'w-full p-4 resize-none focus:outline-none',
                    )}
                    spellCheck={false}
                />
            </div>
        </>
    );
}
