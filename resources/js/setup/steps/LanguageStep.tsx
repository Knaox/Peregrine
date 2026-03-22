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
                <h2 className="text-xl font-semibold text-white">
                    {t('setup.language.title')}
                </h2>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.language.description')}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
                {LANGUAGES.map((lang) => (
                    <button
                        key={lang.code}
                        type="button"
                        onClick={() => handleSelect(lang.code)}
                        className={`flex flex-col items-center justify-center p-8 rounded-xl border-2 transition-all ${
                            data.locale === lang.code
                                ? 'border-orange-500 bg-orange-500/10'
                                : 'border-slate-700 bg-slate-800 hover:border-slate-600'
                        }`}
                    >
                        <span className="text-3xl font-bold text-white mb-2">
                            {lang.flag}
                        </span>
                        <span
                            className={`text-sm font-medium ${
                                data.locale === lang.code
                                    ? 'text-orange-500'
                                    : 'text-slate-300'
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
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
