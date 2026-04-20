import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';
import { useThemeModeStore } from '@/stores/themeModeStore';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

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
    };
    card_config: CardConfig;
    sidebar_config: SidebarConfig;
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
    const { data: theme } = useQuery({
        queryKey: ['theme'],
        queryFn: () => request<ThemeData>('/api/settings/theme'),
        staleTime: 60 * 60 * 1000, // 1 hour
    });
    const effective = useThemeModeStore((s) => s.effective);

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

        // Load Google Font if not system
        const font = theme.data.font;
        if (font && font !== 'system-ui') {
            const fontId = 'theme-google-font';
            if (!document.getElementById(fontId)) {
                const link = document.createElement('link');
                link.id = fontId;
                link.rel = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${font.replace(/ /g, '+')}:wght@300;400;500;600;700&display=swap`;
                document.head.appendChild(link);
            }
            root.style.setProperty('--font-sans', `'${font}', system-ui, sans-serif`);
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

    return <>{children}</>;
}
