import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '@/i18n/config';
import '../css/app.css';

function App() {
    return (
        <div className="min-h-screen bg-slate-900 text-white flex items-center justify-center">
            <h1 className="text-3xl font-bold">Peregrine</h1>
        </div>
    );
}

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(
        <StrictMode>
            <App />
        </StrictMode>
    );
}
