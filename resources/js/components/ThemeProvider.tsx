import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';

interface ThemeData {
    css_variables: Record<string, string>;
    data: {
        custom_css: string;
        font: string;
    };
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
    const { data: theme } = useQuery({
        queryKey: ['theme'],
        queryFn: () => request<ThemeData>('/api/settings/theme'),
        staleTime: 60 * 60 * 1000, // 1 hour
    });

    useEffect(() => {
        if (!theme) return;
        const root = document.documentElement;

        // Apply CSS variables
        Object.entries(theme.css_variables).forEach(([key, value]) => {
            root.style.setProperty(key, value);
        });

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
    }, [theme]);

    return <>{children}</>;
}
