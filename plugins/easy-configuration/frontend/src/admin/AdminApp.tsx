import { Navigate, Route, Routes } from 'react-router-dom';
import { ToastProvider } from '../ui/Toast';
import { TemplateEditorPage } from './TemplateEditorPage';
import { TemplateListPage } from './TemplateListPage';

/**
 * Admin SPA mounted at `/plugins/easy-configuration/*`. Pages live under the
 * `manage` sub-path: the bare `/plugins/easy-configuration` collides with the
 * host's `public/plugins/easy-configuration` asset symlink (nginx 403s on the
 * directory), so we route to `/plugins/easy-configuration/manage` instead.
 * Reached via the "Configure" button on the (admin-only) /admin/plugins page;
 * the API enforces `is_admin`. Routes resolve relative to `/plugins/:pluginId/*`.
 */
export default function AdminApp() {
    return (
        <ToastProvider>
            <div className="ec-root">
                <Routes>
                    <Route path="" element={<Navigate to="manage" replace />} />
                    <Route path="manage" element={<TemplateListPage />} />
                    <Route path="manage/new" element={<TemplateEditorPage />} />
                    <Route path="manage/:templateId" element={<TemplateEditorPage />} />
                </Routes>
            </div>
        </ToastProvider>
    );
}
