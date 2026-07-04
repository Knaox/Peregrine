import { useState } from 'react';
import { Settings2 } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';
import { ToastProvider } from '../ui/Toast';
import { SandboxGeneratorDialog } from './SandboxGeneratorDialog';

/**
 * Slot component registered on the host's startup-variable bridge for
 * `SANDBOX_CODE`: adds a "Generator" button under the variable's input on the
 * core server "Configuration" card, opening the same full-viewport generator
 * overlay as the easy-config field. Edits flow back through the card's normal
 * onChange, so the host's dirty tracking + unified save bar apply untouched.
 * Wrapped in the plugin's own ToastProvider (the host page has none) and the
 * `.ec-root` scope so the plugin stylesheet applies.
 */
export function SandboxVariableControl({
    value,
    onChange,
    disabled,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
}) {
    const { t } = useT();
    const [open, setOpen] = useState(false);

    return (
        <ToastProvider>
            <div className="ec-root">
                <Button variant="secondary" size="sm" aria-haspopup="dialog" onClick={() => setOpen(true)}>
                    <Settings2 size={14} /> {t('sandbox.generator')}
                </Button>
                <SandboxGeneratorDialog
                    open={open}
                    onClose={() => setOpen(false)}
                    value={value}
                    onChange={onChange}
                    disabled={disabled}
                />
            </div>
        </ToastProvider>
    );
}
