import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';

const LANGUAGES = [
    { code: 'en', label: 'English', flag: 'EN' },
    { code: 'fr', label: 'Francais', flag: 'FR' },
] as const;

export function LanguageStep({ data, onChange, onNext }: StepProps) {
    const { t, i18n } = useTranslation();

    const handleSelect = (code: string) => {
        onChange({ locale: code });
        i18n.changeLanguage(code);
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.language.title')}
                </h2>
                <p className="text-[var(--color-text-secondary)] text-sm mt-1">
                    {t('setup.language.description')}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-3 sm:gap-4">
                {LANGUAGES.map((lang) => (
                    <button
                        key={lang.code}
                        type="button"
                        onClick={() => handleSelect(lang.code)}
                        className={`flex flex-col items-center justify-center p-6 sm:p-8 rounded-xl border-2 transition-all ${
                            data.locale === lang.code
                                ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                                : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-hover)]'
                        }`}
                    >
                        <span className="text-3xl font-bold text-[var(--color-text-primary)] mb-2">
                            {lang.flag}
                        </span>
                        <span
                            className={`text-sm font-medium ${
                                data.locale === lang.code
                                    ? 'text-[var(--color-primary)]'
                                    : 'text-[var(--color-text-secondary)]'
                            }`}
                        >
                            {lang.label}
                        </span>
                    </button>
                ))}
            </div>

            <div className="flex justify-end pt-4">
                <button
                    type="button"
                    onClick={onNext}
                    className="px-6 py-3 sm:py-2 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)]"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
