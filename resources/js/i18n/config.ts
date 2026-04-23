import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import en from './en.json';
import fr from './fr.json';

const SUPPORTED = ['en', 'fr'] as const;
type SupportedLocale = typeof SUPPORTED[number];

declare global {
    interface Window {
        __DEFAULT_LOCALE__?: string;
    }
}

// Default language picked by the admin in /admin/settings — injected into
// window by the Blade template so the SPA can boot with it before any
// network request. Falls back to English if the value is missing or not in
// the supported set.
function resolveAdminDefault(): SupportedLocale {
    const raw = (typeof window !== 'undefined' ? window.__DEFAULT_LOCALE__ : undefined) ?? 'en';
    return (SUPPORTED as readonly string[]).includes(raw) ? raw as SupportedLocale : 'en';
}

const adminDefault = resolveAdminDefault();

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            en: { translation: en },
            fr: { translation: fr },
        },
        // Detection order: localStorage (user picked one before) > browser
        // language > admin-configured default > English. The admin default
        // wins over English so a French-only deployment doesn't show English
        // to users who haven't picked a language yet.
        fallbackLng: [adminDefault, 'en'],
        supportedLngs: SUPPORTED as unknown as string[],
        interpolation: {
            escapeValue: false,
        },
        detection: {
            order: ['localStorage', 'navigator'],
            caches: ['localStorage'],
        },
    });

export default i18n;
