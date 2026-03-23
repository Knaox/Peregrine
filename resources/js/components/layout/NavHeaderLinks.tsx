import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { getHeaderIcon, ExternalIcon } from '@/utils/headerIcons';
import type { HeaderLink } from '@/types/Branding';

interface NavHeaderLinksProps {
    links: HeaderLink[];
    mobile?: boolean;
}

function resolveLabel(link: HeaderLink, lang: string): string {
    // Support i18n labels: { label: "Dashboard", label_fr: "Tableau de bord" }
    const langKey = `label_${lang}` as keyof HeaderLink;
    const translated = link[langKey];
    if (typeof translated === 'string' && translated) return translated;
    return link.label;
}

export function NavHeaderLinks({ links, mobile = false }: NavHeaderLinksProps) {
    const location = useLocation();
    const { i18n } = useTranslation();
    const lang = i18n.language.split('-')[0] ?? 'en';
    const isActive = (path: string) => location.pathname === path;

    return (
        <>
            {links.map((hl, i) => {
                const icon = getHeaderIcon(hl.icon);
                const label = resolveLabel(hl, lang);
                const isExternal = hl.new_tab || hl.url.startsWith('http');

                if (!isExternal && hl.url.startsWith('/')) {
                    return (
                        <Link
                            key={i}
                            to={hl.url}
                            className={clsx(
                                'flex items-center gap-1.5 text-sm font-medium transition-all duration-[var(--transition-base)]',
                                mobile
                                    ? clsx(
                                        'rounded-[var(--radius)] px-3 py-2',
                                        isActive(hl.url)
                                            ? 'bg-[var(--color-surface-hover)] text-[var(--color-primary)]'
                                            : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
                                    )
                                    : clsx(
                                        'relative px-3 py-2',
                                        'after:absolute after:bottom-0 after:left-1/2 after:h-0.5 after:-translate-x-1/2',
                                        'after:rounded-full after:bg-[var(--color-primary)] after:transition-all after:duration-[var(--transition-smooth)]',
                                        isActive(hl.url)
                                            ? 'text-[var(--color-primary)] after:w-full'
                                            : 'text-[var(--color-text-secondary)] after:w-0 hover:text-[var(--color-text-primary)] hover:after:w-full',
                                    ),
                            )}
                        >
                            {icon}
                            {label}
                        </Link>
                    );
                }

                return (
                    <a
                        key={i}
                        href={hl.url}
                        target={hl.new_tab ? '_blank' : '_self'}
                        rel={hl.new_tab ? 'noopener noreferrer' : undefined}
                        className={clsx(
                            'flex items-center gap-1.5 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:text-[var(--color-text-primary)]',
                            mobile && 'rounded-[var(--radius)] px-3 py-2 hover:bg-[var(--color-surface-hover)]',
                            !mobile && 'px-3 py-2',
                        )}
                    >
                        {icon}
                        {label}
                        {isExternal && ExternalIcon}
                    </a>
                );
            })}
        </>
    );
}
