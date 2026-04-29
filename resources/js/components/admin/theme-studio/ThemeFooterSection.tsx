import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { ToggleField } from './fields/ToggleField';
import { TextareaField } from './fields/TextareaField';
import type { ThemeDraft } from '@/types/themeStudio.types';

interface ThemeFooterSectionProps {
    draft: ThemeDraft;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

/**
 * Optional footer (off by default) — single line with text + links.
 * Both fields are independent: an admin can show only the text, only
 * links, or both.
 */
export function ThemeFooterSection({ draft, onField }: ThemeFooterSectionProps) {
    const { t } = useTranslation();

    const updateLink = (index: number, patch: Partial<{ label: string; url: string }>) => {
        const next = [...draft.theme_footer_links];
        next[index] = { ...next[index], ...patch };
        onField('theme_footer_links', next);
    };
    const addLink = () => {
        onField('theme_footer_links', [...draft.theme_footer_links, { label: '', url: '' }]);
    };
    const removeLink = (index: number) => {
        const next = draft.theme_footer_links.filter((_, i) => i !== index);
        onField('theme_footer_links', next);
    };

    return (
        <div className="flex flex-col gap-4">
            <ToggleField
                label={t('theme_studio.fields.theme_footer_enabled', 'Show footer')}
                value={draft.theme_footer_enabled}
                onChange={(v) => onField('theme_footer_enabled', v)}
            />
            {draft.theme_footer_enabled && (
                <>
                    <TextareaField
                        label={t('theme_studio.fields.theme_footer_text', 'Footer text')}
                        value={draft.theme_footer_text}
                        rows={2}
                        placeholder={t(
                            'theme_studio.fields.theme_footer_text_placeholder',
                            '© 2026 Acme Corp. All rights reserved.',
                        )}
                        onChange={(v) => onField('theme_footer_text', v)}
                    />
                    <div className="flex flex-col gap-2">
                        <span className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]">
                            {t('theme_studio.fields.theme_footer_links', 'Footer links')}
                        </span>
                        {draft.theme_footer_links.length === 0 && (
                            <p className="text-[11px] text-[var(--color-text-muted)]">
                                {t('theme_studio.fields.theme_footer_links_empty', 'No links yet.')}
                            </p>
                        )}
                        <div className="flex flex-col gap-2">
                            {draft.theme_footer_links.map((link, i) => (
                                <div key={i} className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        value={link.label}
                                        placeholder={t('theme_studio.fields.theme_footer_link_label', 'Label')}
                                        onChange={(e) => updateLink(i, { label: e.target.value })}
                                        className={clsx(
                                            'h-9 w-32 shrink-0 rounded-lg px-2.5 text-[12px]',
                                            'border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                                            'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                                            'focus:outline-none focus:border-[var(--color-primary)]',
                                        )}
                                    />
                                    <input
                                        type="text"
                                        value={link.url}
                                        placeholder="https://…"
                                        onChange={(e) => updateLink(i, { url: e.target.value })}
                                        className={clsx(
                                            'h-9 flex-1 min-w-0 rounded-lg px-2.5 font-mono text-[11px]',
                                            'border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                                            'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                                            'focus:outline-none focus:border-[var(--color-primary)]',
                                        )}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => removeLink(i)}
                                        aria-label={t('common.delete')}
                                        className="h-9 w-9 shrink-0 rounded-lg text-[var(--color-text-muted)] transition-colors hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)] cursor-pointer"
                                    >
                                        ×
                                    </button>
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={addLink}
                            className={clsx(
                                'mt-1 self-start rounded-lg border border-dashed border-[var(--color-border)]',
                                'px-3 py-1.5 text-[11px] font-medium text-[var(--color-text-secondary)]',
                                'transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]',
                                'cursor-pointer',
                            )}
                        >
                            + {t('theme_studio.fields.theme_footer_link_add', 'Add link')}
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}
