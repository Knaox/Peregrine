import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { loadNamespace } from './config';

/**
 * Lazy-loads one or several i18next namespaces at component mount, and
 * reloads them when the active language changes (so flipping en → fr in
 * `/profile` doesn't leave half the page in the previous locale).
 *
 * Page-level components should call this once at the top of their function
 * body, *before* `useTranslation('<ns>')`. The translation hook will return
 * raw keys for one frame while the JSON chunk is fetched, then re-render
 * with translated values — same behavior as any code-split route.
 *
 * Example:
 *     export function ServerConsolePage() {
 *         useNamespace(['server-shell', 'server-console']);
 *         const { t } = useTranslation('server-console');
 *         // ...
 *     }
 */
export function useNamespace(ns: string | readonly string[]): void {
    const { i18n } = useTranslation();
    const list = Array.isArray(ns) ? ns : [ns as string];
    const key = list.join(',');
    useEffect(() => {
        list.forEach((n) => {
            void loadNamespace(n);
        });
    }, [key, i18n.language]);
}
