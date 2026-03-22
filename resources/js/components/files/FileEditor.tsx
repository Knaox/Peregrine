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
                className="fixed inset-0 bg-black/50 z-40"
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
                    'bg-slate-800 border-l border-slate-700',
                    'flex flex-col shadow-2xl',
                )}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                    <div className="flex items-center gap-3 min-w-0">
                        <span className="text-sm text-slate-200 truncate max-w-xs" title={filePath}>
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
                        'flex-1 font-mono text-sm bg-slate-950 text-slate-200',
                        'w-full p-4 resize-none focus:outline-none',
                    )}
                    spellCheck={false}
                />
            </div>
        </>
    );
}
