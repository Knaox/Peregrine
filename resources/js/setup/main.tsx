import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '@/i18n/config';
import '../../css/app.css';
import { SetupWizard } from './SetupWizard';

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(
        <StrictMode>
            <SetupWizard />
        </StrictMode>
    );
}
