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
        <div className="min-h-screen bg-slate-900 text-white">
            <div className="mx-auto max-w-2xl px-4 py-16">
                <h1 className="text-3xl font-bold text-center mb-2">
                    {t('setup.title')}
                </h1>
                <p className="text-slate-400 text-center mb-12">
                    {t('setup.subtitle')}
                </p>

                <StepIndicator currentStep={currentStep} totalSteps={totalSteps} />

                <div className="bg-slate-800 rounded-xl p-8 border border-slate-700">
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
