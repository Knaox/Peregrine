import { Route, Routes } from 'react-router-dom';
import { ToastProvider } from '../ui/Toast';
import { TemplateEditorPage } from './TemplateEditorPage';
import { TemplateListPage } from './TemplateListPage';

/**
 * Admin SPA mounted at `/plugins/easy-configuration/*`. Reached via the
 * "Configure" button on the (admin-only) /admin/plugins page; the API enforces
 * `is_admin`, so non-admins who deep-link here just get empty/unauthorized
 * states. Routes resolve relative to the host's `/plugins/:pluginId/*` route.
 */
export default function AdminApp() {
    return (
        <ToastProvider>
            <div className="ec-root">
                <Routes>
                    <Route path="" element={<TemplateListPage />} />
                    <Route path="new" element={<TemplateEditorPage />} />
                    <Route path=":templateId" element={<TemplateEditorPage />} />
                </Routes>
            </div>
        </ToastProvider>
    );
}
