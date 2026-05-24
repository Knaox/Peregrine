import { useT } from '../../lib/i18n';
import { detectSections, isSectionVisible, toggleSection, type Json } from '../../lib/templateFiles';
import { Toggle } from '../../ui/inputs';

/**
 * Whitelist of the native sections detected in a sectioned config file (ini/toml):
 * toggle off the sections you don't want and only the kept ones are included in
 * the prompt and exposed to the player (empty whitelist = keep them all). Renders
 * nothing for flat files (no sections).
 */
export function SectionWhitelist({ file, onChange }: { file: Json; onChange: (file: Json) => void }) {
    const { t } = useT();
    const sections = detectSections(file);

    if (sections.length === 0) {
        return null;
    }

    return (
        <div className="ec-stack">
            <span className="ec-field-desc ec-secondary">{t('admin.prompt.sections_title')}</span>
            {sections.map((section) => (
                <label key={section} className="ec-row" style={{ cursor: 'pointer', gap: '0.5rem' }}>
                    <Toggle checked={isSectionVisible(file, section)} onChange={() => onChange(toggleSection(file, sections, section))} label={section} />
                    <span className="ec-field-desc ec-truncate">{section}</span>
                </label>
            ))}
        </div>
    );
}
