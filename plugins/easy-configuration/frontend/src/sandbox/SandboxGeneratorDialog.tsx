import { useMemo, useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';
import { Dialog } from '../ui/Dialog';
import { Input } from '../ui/inputs';
import { useToast } from '../ui/Toast';
import {
    decodeSandbox,
    encodeSandbox,
    sandboxDefaults,
    sandboxOption,
    type SandboxState,
    valuesOf,
} from './codec';
import { SandboxOptionsPanel } from './SandboxOptionsPanel';

/**
 * The full-viewport SandboxCode generator overlay (file-editor style), shared
 * by the easy-config field AND the startup-variable slot on the core server
 * page. The raw code stays the single source of truth: the caller passes the
 * current value and receives every re-encoded code through `onChange`; each
 * edit also raises the live replace-in-place notification.
 */
export function SandboxGeneratorDialog({
    open,
    onClose,
    value,
    onChange,
    disabled,
}: {
    open: boolean;
    onClose: () => void;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    const { t } = useT();
    const toast = useToast();
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

    const apply = (code: string): void => {
        onChange(code);
        toast.show(t('sandbox.code_updated'), 'info', { key: 'sbx-code', detail: code });
    };

    const pick = (optionName: string, valueIndex: number): void => {
        const option = sandboxOption(optionName);
        if (!option || decoded.state === null) {
            return;
        }
        apply(encodeSandbox({ ...decoded.state, [optionName]: valuesOf(option)[valueIndex] }));
    };

    const copy = (): void => {
        void navigator.clipboard?.writeText(value).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={t('sandbox.generator_title')}
            size="xl"
            closeLabel={t('common.close')}
            footer={
                <>
                    <span className="sbx-foot-hint">{t('sandbox.dialog_hint')}</span>
                    <Button variant="primary" onClick={onClose}>
                        {t('common.close')}
                    </Button>
                </>
            }
        >
            <div className="ec-dialog-body sbx-dialog-body">
                <div className="sbx-row">
                    <Input
                        value={value}
                        aria-label={t('sandbox.generator_title')}
                        disabled={disabled}
                        invalid={decoded.state === null}
                        spellCheck={false}
                        onChange={(event) => onChange(event.target.value)}
                    />
                    <Button variant="ghost" size="sm" onClick={copy} disabled={value === ''} aria-label={t('sandbox.copy')}>
                        {copied ? <Check size={14} /> : <Copy size={14} />}
                    </Button>
                </div>

                {decoded.state === null && (
                    <p className="sbx-error">
                        {t('sandbox.invalid')}{' '}
                        {disabled !== true && (
                            <button type="button" className="sbx-link" onClick={() => apply(encodeSandbox(sandboxDefaults()))}>
                                {t('sandbox.reset')}
                            </button>
                        )}
                    </p>
                )}
                {decoded.state !== null && decoded.unknownRecords > 0 && (
                    <p className="sbx-hint">{t('sandbox.unknown_records', { count: decoded.unknownRecords })}</p>
                )}

                {decoded.state !== null && (
                    <SandboxOptionsPanel
                        state={decoded.state}
                        disabled={disabled === true}
                        onPick={pick}
                        onResetAll={() => apply(encodeSandbox(sandboxDefaults()))}
                    />
                )}
            </div>
        </Dialog>
    );
}
