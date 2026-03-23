import { useTranslation } from 'react-i18next';
import { useSetupWizard } from './hooks/useSetupWizard';
import { StepIndicator } from './components/StepIndicator';
import { LanguageStep } from './steps/LanguageStep';
import { DatabaseStep } from './steps/DatabaseStep';
import { AdminStep } from './steps/AdminStep';
import { PelicanStep } from './steps/PelicanStep';
import { AuthStep } from './steps/AuthStep';
import { BridgeStep } from './steps/BridgeStep';
import { SummaryStep } from './steps/SummaryStep';
import { AnimatedBackground } from '@/components/AnimatedBackground';
import type { StepProps } from './types';
import type { ComponentType } from 'react';

const STEP_COMPONENTS: ComponentType<StepProps>[] = [
    LanguageStep,
    DatabaseStep,
    AdminStep,
    PelicanStep,
    AuthStep,
    BridgeStep,
    SummaryStep,
];

export function SetupWizard() {
    const { t } = useTranslation();
    const { currentStep, totalSteps, data, goNext, goPrevious, updateData } = useSetupWizard();

    const StepComponent = STEP_COMPONENTS[currentStep];

    return (
        <div className="relative min-h-screen text-[var(--color-text-primary)]">
            <AnimatedBackground />

            <div className="relative z-10 mx-auto max-w-2xl px-4 py-16">
                {/* Logo + Title */}
                <div className="mb-12 text-center">
                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--color-primary)]/10 ring-1 ring-[var(--color-primary)]/20">
                        <img src="/images/logo.webp" alt="Logo" className="h-10 w-10" />
                    </div>
                    <h1 className="text-3xl font-bold">
                        {t('setup.title')}
                    </h1>
                    <p className="mt-2 text-[var(--color-text-secondary)]">
                        {t('setup.subtitle')}
                    </p>
                </div>

                <StepIndicator currentStep={currentStep} totalSteps={totalSteps} />

                <div className="rounded-2xl border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-8 shadow-[var(--shadow-lg)] backdrop-blur-xl">
                    {StepComponent && (
                        <StepComponent
                            data={data}
                            onChange={updateData}
                            onNext={goNext}
                            onPrevious={goPrevious}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
