import { pickLabel, useT } from '../../../lib/i18n';
import { detectSections, flattenParams, isSectionVisible, sectionLabel, setSectionLabel, toggleSection, type Json } from '../../../lib/templateFiles';
import type { LocaleLabel } from '../../../types';
import { Input, Toggle } from '../../../ui/inputs';
import { ParameterCard } from './ParameterCard';

interface Props {
    file: Json;
    datalistId: string;
    lang: string;
    onChange: (file: Json) => void;
}

/**
 * Visual editor for a single template file: a header, the flat parameters, then
 * one block per native section (ini/toml) with its friendly FR/EN label and a
 * visibility toggle, followed by the section's parameter cards. Section helpers
 * are reused from templateFiles so this fully absorbs the old Links & sections
 * panel.
 */
export function VisualFileEditor({ file, datalistId, lang, onChange }: Props) {
    const { t } = useT();
    const title = pickLabel((file.label ?? null) as LocaleLabel | null, lang, String(file.path ?? ''));
    const rows = flattenParams(file);
    const sections = detectSections(file);
    const flatKeys = rows.filter((r) => r.section === null).map((r) => r.key);

    const renderCard = (section: string | null, key: string) => (
        <ParameterCard key={`${section ?? ''}:${key}`} file={file} section={section} paramKey={key} datalistId={datalistId} lang={lang} onChange={onChange} />
    );

    return (
        <div className="ec-stack">
            <div className="ec-between">
                <h3 className="ec-title">{title}</h3>
                <span className="ec-field-desc ec-muted">{String(file.path ?? '')}</span>
            </div>

            {flatKeys.map((key) => renderCard(null, key))}

            {sections.map((section) => (
                <div key={section} className="ec-card ec-stack">
                    <div className="ec-section-row">
                        <label className="ec-row" style={{ cursor: 'pointer', gap: '0.5rem' }}>
                            <Toggle checked={isSectionVisible(file, section)} onChange={() => onChange(toggleSection(file, sections, section))} label={t('admin.visual.section_visible')} />
                            <span className="ec-field-desc ec-secondary ec-truncate">{section}</span>
                        </label>
                        <Input value={sectionLabel(file, section, 'en')} placeholder={t('admin.editor.links_section_en')} onChange={(e) => onChange(setSectionLabel(file, section, 'en', e.target.value))} />
                        <Input value={sectionLabel(file, section, 'fr')} placeholder={t('admin.editor.links_section_fr')} onChange={(e) => onChange(setSectionLabel(file, section, 'fr', e.target.value))} />
                    </div>
                    {rows.filter((r) => r.section === section).map((r) => renderCard(section, r.key))}
                </div>
            ))}
        </div>
    );
}
