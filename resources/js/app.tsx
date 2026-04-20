import '@/plugins/shared';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MotionProvider } from '@/components/MotionProvider';
import { ThemeProvider } from '@/components/ThemeProvider';
import { PluginLoader } from '@/plugins/PluginLoader';
import { PluginPageRenderer } from '@/plugins/PluginPageRenderer';
import '@/i18n/config';
import '../css/app.css';
import { LoginPage } from '@/pages/LoginPage';
import { RegisterPage } from '@/pages/RegisterPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { AppLayout } from '@/components/AppLayout';
import { ProtectedRoute } from '@/components/ProtectedRoute';
import { ServerDetailPage } from '@/pages/ServerDetailPage';
import { ServerOverviewPage } from '@/pages/ServerOverviewPage';
import { ServerConsolePage } from '@/pages/ServerConsolePage';
import { ServerFilesPage } from '@/pages/ServerFilesPage';
import { ServerSftpPage } from '@/pages/ServerSftpPage';
import { ProfilePage } from '@/pages/ProfilePage';
import { InviteAcceptPage } from '@/pages/InviteAcceptPage';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            retry: 1,
        },
    },
});

function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <ThemeProvider>
                <MotionProvider>
                    <BrowserRouter>
                        <PluginLoader />
                        <Routes>
                            <Route path="/login" element={<LoginPage />} />
                            <Route path="/register" element={<RegisterPage />} />
                            <Route path="/invite/:token" element={<InviteAcceptPage />} />
                            <Route element={<ProtectedRoute />}>
                                <Route element={<AppLayout />}>
                                    <Route path="/" element={<Navigate to="/dashboard" replace />} />
                                    <Route path="/dashboard" element={<DashboardPage />} />
                                    <Route path="/profile" element={<ProfilePage />} />
                                    <Route path="/plugins/:pluginId/*" element={<PluginPageRenderer />} />
                                </Route>
                                <Route path="/servers/:id/*" element={<ServerDetailPage />} />
                            </Route>
                        </Routes>
                    </BrowserRouter>
                </MotionProvider>
            </ThemeProvider>
        </QueryClientProvider>
    );
}

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(
        <StrictMode>
            <App />
        </StrictMode>
    );

    // Hide splash screen once React has mounted
    requestAnimationFrame(() => {
        const splash = document.getElementById('splash');
        if (splash) {
            splash.classList.add('hidden');
            setTimeout(() => splash.remove(), 300);
        }
    });
}
