import { useT } from '../../../lib/i18n';
import { detectSections, flattenParams, isSectionVisible, sectionLabel, setExpandedByDefault, setFileField, setSectionLabel, toggleSection, type Json } from '../../../lib/templateFiles';
import { Input, Select, Toggle } from '../../../ui/inputs';
import { ParameterCard } from './ParameterCard';

const FILE_FORMATS = ['properties', 'ini', 'yaml', 'json', 'toml', 'palworld', 'theforest', 'xml', 'xml-property'] as const;

interface Props {
    file: Json;
    datalistId: string;
    lang: string;
    onChange: (file: Json) => void;
}

/**
 * Visual editor for a single template file: an editable header (id, path,
 * format, expanded-by-default), the flat parameters, then one block per native
 * section (ini/toml) with its friendly FR/EN label and a visibility toggle,
 * followed by the section's parameter cards. Section helpers are reused from
 * templateFiles so this fully absorbs the old Links & sections panel.
 */
export function VisualFileEditor({ file, datalistId, lang, onChange }: Props) {
    const { t } = useT();
    const rows = flattenParams(file);
    const sections = detectSections(file);
    const flatKeys = rows.filter((r) => r.section === null).map((r) => r.key);

    const renderCard = (section: string | null, key: string) => (
        <ParameterCard key={`${section ?? ''}:${key}`} file={file} section={section} paramKey={key} datalistId={datalistId} lang={lang} onChange={onChange} />
    );

    return (
        <div className="ec-stack">
            <div className="ec-card ec-stack">
                <div className="ec-section-row">
                    <Input value={String(file.id ?? '')} placeholder={t('admin.visual.file_id')} onChange={(e) => onChange(setFileField(file, 'id', e.target.value))} />
                    <Input value={String(file.path ?? '')} placeholder={t('admin.visual.file_path')} onChange={(e) => onChange(setFileField(file, 'path', e.target.value))} />
                    <Select value={String(file.format ?? 'properties')} onChange={(value) => onChange(setFileField(file, 'format', value))}>
                        {FILE_FORMATS.map((format) => (
                            <option key={format} value={format}>
                                {format}
                            </option>
                        ))}
                    </Select>
                </div>
                <label className="ec-row" style={{ cursor: 'pointer', gap: '0.5rem' }}>
                    <Toggle checked={file.expanded_by_default === true} onChange={(on) => onChange(setExpandedByDefault(file, on))} label={t('admin.visual.expanded_by_default')} />
                    <span className="ec-field-desc ec-secondary">{t('admin.visual.expanded_by_default')}</span>
                </label>
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
