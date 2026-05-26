import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { useNamespace } from '@/i18n/useNamespace';

const Icon = ({ d, size = 14 }: { d: string; size?: number }) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
    >
        <path d={d} />
    </svg>
);

const EXPORT_ICON = 'M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4 M7 10l5 5 5-5 M12 15V3';
const IMPORT_ICON = 'M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4 M17 8l-5-5-5 5 M12 3v12';

interface ThemeTransferButtonsProps {
    onExport: () => void;
    onImport: () => void;
    disabled?: boolean;
}

/**
 * Export / Import pair for the Theme Studio header. Export downloads the
 * current draft as a CLI-compatible JSON file; Import loads a JSON file into
 * the draft (the admin then Publishes). Extracted from ThemeStudioPage so
 * that page stays under the 300-line cap.
 */
export function ThemeTransferButtons({ onExport, onImport, disabled }: ThemeTransferButtonsProps) {
    useNamespace(["theme-studio"] as const);
    const { t } = useTranslation();

    return (
        <>
            <Button variant="ghost" size="sm" disabled={disabled} onClick={onImport}>
                <Icon d={IMPORT_ICON} />
                {t('theme-studio:import', 'Import')}
            </Button>
            <Button variant="ghost" size="sm" disabled={disabled} onClick={onExport}>
                <Icon d={EXPORT_ICON} />
                {t('theme-studio:export', 'Export')}
            </Button>
        </>
    );
}
