import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
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
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route element={<ProtectedRoute />}>
                        <Route element={<AppLayout />}>
                            <Route path="/" element={<Navigate to="/dashboard" replace />} />
                            <Route path="/dashboard" element={<DashboardPage />} />
                            <Route path="/profile" element={<ProfilePage />} />
                        </Route>
                        <Route element={<AppLayout />}>
                            <Route path="/servers/:id" element={<ServerDetailPage />}>
                                <Route index element={<ServerOverviewPage />} />
                                <Route path="console" element={<ServerConsolePage />} />
                                <Route path="files" element={<ServerFilesPage />} />
                                <Route path="sftp" element={<ServerSftpPage />} />
                            </Route>
                        </Route>
                    </Route>
                </Routes>
            </BrowserRouter>
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
}
