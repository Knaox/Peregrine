import { useTranslation } from 'react-i18next';

interface StepIndicatorProps {
    currentStep: number;
    totalSteps: number;
}

const STEP_KEYS = [
    'setup.steps.language',
    'setup.steps.database',
    'setup.steps.admin',
    'setup.steps.pelican',
    'setup.steps.auth',
    'setup.steps.bridge',
    'setup.steps.summary',
] as const;

export function StepIndicator({ currentStep, totalSteps }: StepIndicatorProps) {
    const { t } = useTranslation();

    return (
        <nav className="mb-12">
            <ol className="flex items-center gap-2">
                {STEP_KEYS.slice(0, totalSteps).map((key, index) => (
                    <li key={key} className="flex items-center gap-2 flex-1">
                        <div className="flex flex-col items-center flex-1">
                            <div
                                className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors ${
                                    index === currentStep
                                        ? 'bg-orange-500 text-white'
                                        : index < currentStep
                                          ? 'bg-green-500 text-white'
                                          : 'bg-slate-700 text-slate-400'
                                }`}
                            >
                                {index < currentStep ? (
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : (
                                    index + 1
                                )}
                            </div>
                            <span
                                className={`text-xs mt-1 text-center transition-colors ${
                                    index === currentStep
                                        ? 'text-orange-500 font-semibold'
                                        : index < currentStep
                                          ? 'text-green-500'
                                          : 'text-slate-500'
                                }`}
                            >
                                {t(key)}
                            </span>
                        </div>
                        {index < totalSteps - 1 && (
                            <div
                                className={`h-px flex-1 mt-[-20px] transition-colors ${
                                    index < currentStep ? 'bg-green-500' : 'bg-slate-700'
                                }`}
                            />
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
