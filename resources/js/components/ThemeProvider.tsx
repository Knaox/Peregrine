import { createContext, useContext, useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';
import { useThemeModeStore } from '@/stores/themeModeStore';
import { useThemePreviewBridge } from '@/hooks/useThemePreviewBridge';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

export interface ThemeLayoutData {
    header_height: number;
    header_sticky: boolean;
    header_align: 'default' | 'centered' | 'split';
    container_max: '1280' | '1440' | '1536' | 'full';
    page_padding: 'compact' | 'comfortable' | 'spacious';
}

export interface ThemeSidebarAdvancedData {
    classic_width: number;
    rail_width: number;
    mobile_width: number;
    blur_intensity: number;
    floating: boolean;
}

export type LoginBackgroundPattern =
    | 'none'
    | 'gradient'
    | 'mesh'
    | 'dots'
    | 'grid'
    | 'aurora'
    | 'orbs'
    | 'noise';

export interface ThemeLoginData {
    template: 'centered' | 'split' | 'overlay' | 'minimal';
    background_image: string;
    background_blur: number;
    background_pattern: LoginBackgroundPattern;
}

export interface ThemePageOverridesData {
    console_fullwidth: boolean;
    files_fullwidth: boolean;
    dashboard_expanded: boolean;
}

export interface ThemeFooterData {
    enabled: boolean;
    text: string;
    links: Array<{ label: string; url: string }>;
}

export interface ThemeAppData {
    background_pattern: LoginBackgroundPattern;
}

export interface ThemeData {
    css_variables: Record<string, string>;
    mode_variants?: {
        dark: Record<string, string>;
        light: Record<string, string>;
    };
    data: {
        custom_css: string;
        font: string;
        mode?: string;
        /** Layout shell descriptors. Drives data-* attributes on <html>. */
        layout?: ThemeLayoutData;
        sidebar_advanced?: ThemeSidebarAdvancedData;
        login?: ThemeLoginData;
        page_overrides?: ThemePageOverridesData;
        footer?: ThemeFooterData;
        app?: ThemeAppData;
    };
    card_config: CardConfig;
    sidebar_config: SidebarConfig;
}

/**
 * React Context exposing the *resolved* theme — preview-aware. All
 * descendants of ThemeProvider read the same instance via the
 * `useResolvedTheme()` hook, which prevents the per-consumer
 * postMessage-listener duplication we used to have (each ServerCard
 * spawned its own bridge → up to 5+ listeners + 5+ "ready" messages
 * fired on iframe boot, with non-deterministic update propagation).
 */
const ThemeContext = createContext<ThemeData | null>(null);

export function useThemeContext(): ThemeData | null {
    return useContext(ThemeContext);
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
    const preview = useThemePreviewBridge();

    const { data: apiTheme } = useQuery({
        queryKey: ['theme'],
        queryFn: () => request<ThemeData>('/api/settings/theme'),
        staleTime: 60 * 60 * 1000, // 1 hour
        enabled: !preview.enabled,
    });

    const storeMode = useThemeModeStore((s) => s.effective);

    const theme = preview.enabled ? preview.theme : apiTheme;
    const effective = preview.mode ?? storeMode;
    const contextValue = useMemo<ThemeData | null>(() => theme ?? null, [theme]);

    useEffect(() => {
        if (!theme) return;
        const root = document.documentElement;

        // Pick the right variable set: mode_variants has both dark/light for
        // the active brand preset. If the user mode matches the admin-saved
        // global mode, the fallback css_variables is identical.
        const variantSet = theme.mode_variants?.[effective] ?? theme.css_variables;

        Object.entries(variantSet).forEach(([key, value]) => {
            root.style.setProperty(key, value);
        });

        // data-theme attribute + body class let CSS selectors branch per mode.
        root.setAttribute('data-theme', effective);
        if (effective === 'light') document.body.classList.add('theme-light');
        else document.body.classList.remove('theme-light');

        // Load Google Font if not system. We append (not replace) so a user
        // changing fonts during a studio session pulls the new family without
        // a full reload — the previous link stays cached but unused.
        const font = theme.data.font;
        if (font && font !== 'system-ui') {
            const fontId = `theme-google-font-${font.replace(/\s+/g, '-')}`;
            if (!document.getElementById(fontId)) {
                const link = document.createElement('link');
                link.id = fontId;
                link.rel = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${font.replace(/ /g, '+')}:wght@300;400;500;600;700&display=swap`;
                document.head.appendChild(link);
            }
            root.style.setProperty('--font-sans', `'${font}', system-ui, sans-serif`);
        } else if (font === 'system-ui') {
            root.style.setProperty('--font-sans', 'system-ui, sans-serif');
        }

        // Layout shell — apply data-attributes consumed by `app.css` rules
        // (sticky vs static header, header alignment variants). The matching
        // CSS variables for sizes / paddings are already in css_variables.
        const layout = theme.data.layout;
        if (layout) {
            root.setAttribute('data-header-sticky', layout.header_sticky ? 'true' : 'false');
            root.setAttribute('data-header-align', layout.header_align);
        }
        // Sidebar in-server (Vague 3 complète) — floating style is a CSS
        // toggle on the <aside> via data-attr.
        const sidebarAdv = theme.data.sidebar_advanced;
        if (sidebarAdv) {
            root.setAttribute('data-sidebar-floating', sidebarAdv.floating ? 'true' : 'false');
        }
        // Per-page overrides (Vague 3 complète) — flags exposed as data-attrs
        // so individual pages' wrappers can opt into a wider layout via CSS.
        const pageOv = theme.data.page_overrides;
        if (pageOv) {
            root.setAttribute('data-page-console-fullwidth', pageOv.console_fullwidth ? 'true' : 'false');
            root.setAttribute('data-page-files-fullwidth', pageOv.files_fullwidth ? 'true' : 'false');
            root.setAttribute('data-page-dashboard-expanded', pageOv.dashboard_expanded ? 'true' : 'false');
        }

        // Apply custom CSS
        let styleEl = document.getElementById('theme-custom-css');
        if (theme.data.custom_css) {
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'theme-custom-css';
                document.head.appendChild(styleEl);
            }
            styleEl.textContent = theme.data.custom_css;
        } else if (styleEl) {
            styleEl.remove();
        }
    }, [theme, effective]);

    return <ThemeContext.Provider value={contextValue}>{children}</ThemeContext.Provider>;
}
