import { useTranslation } from 'react-i18next';
import { useBranding } from '@/hooks/useBranding';
import type { ThemeFooterData } from '@/components/ThemeProvider';

interface AppFooterProps {
    footer: ThemeFooterData;
}

/**
 * Optional footer rendered at the bottom of AppLayout. Off by default
 * (theme_footer_enabled=false) — admins opt-in from /theme-studio.
 *
 * Single-line layout: optional text on the left, optional links on the
 * right. Both grow to fill width. Mobile stacks vertically.
 *
 * When the toggle is on but no text/links are filled in, we fall back to
 * a minimal "© {year} {appName}" line so the admin sees the footer they
 * asked for instead of nothing (which made the toggle feel broken).
 */
export function AppFooter({ footer }: AppFooterProps) {
    const { t } = useTranslation();
    const branding = useBranding();
    if (!footer.enabled) return null;

    const fallbackText = t('app_footer.fallback', {
        defaultValue: '© {{year}} {{name}}',
        year: new Date().getFullYear(),
        name: branding.app_name ?? 'Peregrine',
    });
    const text = footer.text || fallbackText;

    return (
        <footer
            className="relative z-10 border-t border-[var(--color-border)]/40 px-6 py-4 text-[12px] text-[var(--color-text-muted)]"
            style={{ background: 'var(--color-surface)/40' }}
        >
            <div
                className="mx-auto flex flex-col items-center justify-between gap-3 sm:flex-row"
                style={{ maxWidth: 'var(--layout-container-max)' }}
            >
                <span className="text-center sm:text-left">{text}</span>
                {footer.links.length > 0 && (
                    <ul className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                        {footer.links.map((link, i) => (
                            <li key={`${link.url}-${i}`}>
                                <a
                                    href={link.url}
                                    className="transition-colors hover:text-[var(--color-text-primary)]"
                                    target={link.url.startsWith('http') ? '_blank' : undefined}
                                    rel={link.url.startsWith('http') ? 'noopener noreferrer' : undefined}
                                >
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </footer>
    );
}
