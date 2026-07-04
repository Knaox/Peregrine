import { useMemo, useState } from 'react';
import { Check, Copy, Settings2 } from 'lucide-react';
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
 * Control wired to `config.generator === '7dtd-sandbox'`: the raw SandboxCode
 * stays the single source of truth (it is what gets saved to the file), and a
 * full-viewport generator overlay — the same file-editor-style surface as the
 * Files page — re-encodes it on every change, hosthavoc-style. Every edit also
 * raises a live keyed notification with the fresh code. Pasting a code (inline
 * or in the overlay) re-hydrates the options; an unreadable code falls back to
 * a plain text field with a one-click reset.
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

    // Live feedback: every option change re-raises ONE keyed notification
    // showing the freshly generated code (same-key toasts replace in place).
    const announce = (code: string): void => {
        toast.show(t('sandbox.code_updated'), 'info', { key: 'sbx-code', detail: code });
    };

    const apply = (code: string): void => {
        onChange(code);
        announce(code);
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

    const codeRow = (
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
        </div>
    );

    const problems = (
        <>
            {decoded.state === null && (
                <p className="sbx-error">
                    {t('sandbox.invalid')}{' '}
                    {!disabled && (
                        <button type="button" className="sbx-link" onClick={() => apply(encodeSandbox(sandboxDefaults()))}>
                            {t('sandbox.reset')}
                        </button>
                    )}
                </p>
            )}
            {decoded.state !== null && decoded.unknownRecords > 0 && (
                <p className="sbx-hint">{t('sandbox.unknown_records', { count: decoded.unknownRecords })}</p>
            )}
        </>
    );

    return (
        <div className="sbx">
            <div className="sbx-row">
                {codeRow}
                <Button variant="secondary" size="sm" aria-haspopup="dialog" onClick={() => setOpenPanel(true)}>
                    <Settings2 size={14} /> {t('sandbox.generator')}
                </Button>
            </div>
            {problems}

            <Dialog
                open={openPanel}
                onClose={() => setOpenPanel(false)}
                title={t('sandbox.generator_title')}
                size="xl"
                closeLabel={t('common.close')}
                footer={
                    <>
                        <span className="sbx-foot-hint">{t('sandbox.dialog_hint')}</span>
                        <Button variant="primary" onClick={() => setOpenPanel(false)}>
                            {t('common.close')}
                        </Button>
                    </>
                }
            >
                <div className="ec-dialog-body sbx-dialog-body">
                    {codeRow}
                    {problems}
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
        </div>
    );
}
