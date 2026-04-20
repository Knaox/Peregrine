import { useParams } from 'react-router-dom';
import { usePluginStore } from '@/plugins/pluginStore';
import { Spinner } from '@/components/ui/Spinner';

export function PluginPageRenderer() {
    const { pluginId } = useParams<{ pluginId: string }>();
    const { getComponent, isLoading } = usePluginStore();

    if (!pluginId) {
        return <div className="p-8 text-center text-[var(--color-text-muted)]">Plugin not found.</div>;
    }

    const Component = getComponent(pluginId);

    if (isLoading && !Component) {
        return (
            <div className="flex items-center justify-center py-20">
                <Spinner size="lg" />
            </div>
        );
    }

    if (!Component) {
        return (
            <div className="p-8 text-center text-[var(--color-text-muted)]">
                Plugin &quot;{pluginId}&quot; is not loaded.
            </div>
        );
    }

    return <Component />;
}
