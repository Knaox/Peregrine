import { useMemo, useState } from 'react';
import { Check, Copy, Settings2 } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';
import { Input } from '../ui/inputs';
import { useToast } from '../ui/Toast';
import { decodeSandbox, encodeSandbox, sandboxDefaults, type SandboxState } from './codec';
import { SandboxGeneratorDialog } from './SandboxGeneratorDialog';

/**
 * Control wired to `config.generator === '7dtd-sandbox'`: the raw SandboxCode
 * stays the single source of truth (it is what gets saved to the file), and
 * the shared full-viewport generator overlay re-encodes it on every change.
 * Pasting a code re-hydrates the options; an unreadable code falls back to a
 * plain text field with a one-click reset.
 */
export function SandboxCodeField({
    value,
    onChange,
    disabled,
    invalid,
    ariaLabel,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel?: string;
}) {
    const { t } = useT();
    const toast = useToast();
    const [openPanel, setOpenPanel] = useState(false);
    const [copied, setCopied] = useState(false);

    const decoded = useMemo((): { state: SandboxState | null; unknownRecords: number } => {
        if (value.trim() === '') {
            return { state: sandboxDefaults(), unknownRecords: 0 };
        }
        try {
            return decodeSandbox(value);
        } catch {
            return { state: null, unknownRecords: 0 };
        }
    }, [value]);

    const applyReset = (): void => {
        const code = encodeSandbox(sandboxDefaults());
        onChange(code);
        toast.show(t('sandbox.code_updated'), 'info', { key: 'sbx-code', detail: code });
    };

    const copy = (): void => {
        void navigator.clipboard?.writeText(value).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div className="sbx">
            <div className="sbx-row">
                <Input
                    value={value}
                    aria-label={ariaLabel}
                    disabled={disabled}
                    invalid={invalid || decoded.state === null}
                    spellCheck={false}
                    onChange={(event) => onChange(event.target.value)}
                />
                <Button variant="ghost" size="sm" onClick={copy} disabled={value === ''} aria-label={t('sandbox.copy')}>
                    {copied ? <Check size={14} /> : <Copy size={14} />}
                </Button>
                <Button variant="secondary" size="sm" aria-haspopup="dialog" onClick={() => setOpenPanel(true)}>
                    <Settings2 size={14} /> {t('sandbox.generator')}
                </Button>
            </div>

            {decoded.state === null && (
                <p className="sbx-error">
                    {t('sandbox.invalid')}{' '}
                    {!disabled && (
                        <button type="button" className="sbx-link" onClick={applyReset}>
                            {t('sandbox.reset')}
                        </button>
                    )}
                </p>
            )}
            {decoded.state !== null && decoded.unknownRecords > 0 && (
                <p className="sbx-hint">{t('sandbox.unknown_records', { count: decoded.unknownRecords })}</p>
            )}

            <SandboxGeneratorDialog
                open={openPanel}
                onClose={() => setOpenPanel(false)}
                value={value}
                onChange={onChange}
                disabled={disabled}
            />
        </div>
    );
}
