import { useState, useCallback, useEffect } from 'react';
import i18n from '@/i18n/config';
import type { SetupState } from '../types';
import { getSetupState } from '../services/setupApi';

// Wizard sequence: Langue → Database → Admin → Pelican → Backfill → Webhook → Auth → Summary
// Keep in sync with STEP_COMPONENTS in SetupWizard.tsx — adding a step here without
// the matching component (or vice versa) leaves the wizard with a blank panel.
const TOTAL_STEPS = 8;

// Index of the BackfillStep in STEP_COMPONENTS — the place we resume to
// when the SPA reloads mid-wizard with the install already done. Keep
// in sync with SetupWizard.tsx STEP_COMPONENTS.
const POST_INSTALL_STEP = 6;

const STORAGE_KEY = 'peregrine.setup_wizard.state';

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
};

interface PersistedState {
    currentStep: number;
    // Form data deliberately excluded from persistence — passwords don't
    // belong in localStorage. Only currentStep is restored.
}

function loadPersistedStep(): number {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (! raw) return 0;
        const parsed = JSON.parse(raw) as PersistedState;
        const step = Number(parsed.currentStep);
        return Number.isInteger(step) && step >= 0 && step < TOTAL_STEPS ? step : 0;
    } catch {
        return 0;
    }
}

function persistStep(step: number): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ currentStep: step }));
    } catch {
        // Best-effort — Safari private mode etc. throws on setItem.
    }
}

function clearPersistedStep(): void {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // best-effort
    }
}

export function useSetupWizard() {
    const [currentStep, setCurrentStep] = useState<number>(() => loadPersistedStep());
    const [data, setData] = useState<SetupState>(initialState);

    // On mount, ask the backend whether the install already ran. If yes
    // and the wizard is in the "finishing" phase (sentinel still present),
    // jump straight to the post-install step so a `php artisan serve`
    // restart triggered by the install's .env write doesn't dump the
    // admin back at step 0 with their progress wiped.
    useEffect(() => {
        let cancelled = false;
        getSetupState().then((state) => {
            if (cancelled) return;
            if (state.installed && state.finishing) {
                setCurrentStep((prev) => (prev < POST_INSTALL_STEP ? POST_INSTALL_STEP : prev));
            }
        }).catch(() => {
            // Endpoint unavailable (older backend) → keep whatever step we
            // restored from localStorage.
        });
        return () => { cancelled = true; };
    }, []);

    // Persist every step transition so a reload preserves progress. The
    // sequence-of-events that prompted this : install POST writes .env,
    // `php artisan serve` detects the change and restarts, the dev server
    // is briefly down, the SPA stays loaded but might reload on the next
    // user interaction — without persistence we lose currentStep.
    useEffect(() => {
        persistStep(currentStep);
    }, [currentStep]);

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

// Exposed so WebhookStep can clear the persisted state on Finish — the
// next visit to /setup (which by then will redirect anyway) won't carry
// stale step data.
export { clearPersistedStep };
