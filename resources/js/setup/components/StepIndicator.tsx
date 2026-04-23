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
    'setup.steps.summary',
] as const;

export function StepIndicator({ currentStep, totalSteps }: StepIndicatorProps) {
    const { t } = useTranslation();

    return (
        <nav className="mb-8">
            <ol className="flex items-center gap-2">
                {STEP_KEYS.slice(0, totalSteps).map((key, index) => (
                    <li key={key} className="flex items-center gap-2 flex-1">
                        <div className="flex flex-col items-center flex-1">
                            <div
                                className={`w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-[var(--transition-smooth)] ${
                                    index === currentStep
                                        ? 'bg-[var(--color-primary)] text-white shadow-[var(--shadow-glow)] ring-2 ring-[var(--color-primary)]/30'
                                        : index < currentStep
                                          ? 'bg-[var(--color-success)] text-white'
                                          : 'bg-[var(--color-surface)] text-[var(--color-text-muted)] ring-1 ring-[var(--color-border)]'
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
                                className={`text-[0.65rem] mt-1.5 text-center font-medium transition-colors duration-[var(--transition-smooth)] ${
                                    index === currentStep
                                        ? 'text-[var(--color-primary)]'
                                        : index < currentStep
                                          ? 'text-[var(--color-success)]'
                                          : 'text-[var(--color-text-muted)]'
                                }`}
                            >
                                {t(key)}
                            </span>
                        </div>
                        {index < totalSteps - 1 && (
                            <div
                                className={`h-px flex-1 mt-[-20px] transition-colors duration-[var(--transition-smooth)] ${
                                    index < currentStep ? 'bg-[var(--color-success)]' : 'bg-[var(--color-border)]'
                                }`}
                            />
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
