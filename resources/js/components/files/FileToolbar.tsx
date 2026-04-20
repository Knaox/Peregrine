import { useTranslation } from 'react-i18next';
import { type FileToolbarProps } from '@/components/files/FileToolbar.props';
import { Button } from '@/components/ui/Button';
import { IconButton } from '@/components/ui/IconButton';

export function FileToolbar({ onNewFile, onNewFolder, onRefresh, onPull, canCreate = true }: FileToolbarProps) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-2">
            {canCreate && (
                <>
                    <Button variant="secondary" size="sm" onClick={onNewFile} className="hidden sm:inline-flex">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {t('servers.files.new_file')}
                    </Button>

                    <Button variant="secondary" size="sm" onClick={onNewFolder} className="hidden sm:inline-flex">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        {t('servers.files.new_folder')}
                    </Button>

                    {onPull && (
                        <Button variant="secondary" size="sm" onClick={onPull} className="hidden sm:inline-flex">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            {t('servers.files.pull')}
                        </Button>
                    )}

                    {/* Mobile: icon-only buttons */}
                    <IconButton
                        icon={<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>}
                        size="sm"
                        title={t('servers.files.new_file')}
                        onClick={onNewFile}
                        className="sm:hidden"
                    />
                </>
            )}

            <IconButton
                icon={
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                }
                size="sm"
                title={t('servers.files.refresh')}
                onClick={onRefresh}
            />
        </div>
    );
}
