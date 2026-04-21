import { useState, useCallback } from 'react';
import i18n from '@/i18n/config';
import type { SetupState } from '../types';

const TOTAL_STEPS = 7;

const detectedLocale = (i18n.language ?? 'en').startsWith('fr') ? 'fr' : 'en';

const initialState: SetupState = {
    locale: detectedLocale,
    database: {
        host: 'localhost',
        port: 3306,
        database: 'peregrine',
        username: 'root',
        password: '',
    },
    admin: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
    pelican: {
        url: '',
        api_key: '',
        client_api_key: '',
    },
    auth: {
        allow_local_registration: true,
    },
    bridge: {
        enabled: false,
        stripe_webhook_secret: '',
    },
};

export function useSetupWizard() {
    const [currentStep, setCurrentStep] = useState(0);
    const [data, setData] = useState<SetupState>(initialState);

    const goNext = useCallback(() => {
        setCurrentStep((prev) => Math.min(prev + 1, TOTAL_STEPS - 1));
    }, []);

    const goPrevious = useCallback(() => {
        setCurrentStep((prev) => Math.max(prev - 1, 0));
    }, []);

    const updateData = useCallback((updates: Partial<SetupState>) => {
        setData((prev) => ({ ...prev, ...updates }));
    }, []);

    return {
        currentStep,
        totalSteps: TOTAL_STEPS,
        data,
        goNext,
        goPrevious,
        updateData,
    };
}
