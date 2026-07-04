import { useMemo, useState } from 'react';
import { Check, Copy, Settings2 } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';
import { Input } from '../ui/inputs';
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
 * stays the single source of truth (it is what gets saved to the file), and an
 * inline generator panel — the same options the game's sandbox screen offers —
 * re-encodes it on every change, hosthavoc-style. Pasting a code into the text
 * field re-hydrates the panel; an unreadable code falls back to a plain text
 * field with a one-click reset.
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

    const pick = (optionName: string, valueIndex: number): void => {
        const option = sandboxOption(optionName);
        if (!option || decoded.state === null) {
            return;
        }
        onChange(encodeSandbox({ ...decoded.state, [optionName]: valuesOf(option)[valueIndex] }));
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
                <Button variant="secondary" size="sm" aria-expanded={openPanel} onClick={() => setOpenPanel((current) => !current)}>
                    <Settings2 size={14} /> {t('sandbox.generator')}
                </Button>
            </div>

            {decoded.state === null && (
                <p className="sbx-error">
                    {t('sandbox.invalid')}{' '}
                    {!disabled && (
                        <button type="button" className="sbx-link" onClick={() => onChange(encodeSandbox(sandboxDefaults()))}>
                            {t('sandbox.reset')}
                        </button>
                    )}
                </p>
            )}
            {decoded.state !== null && decoded.unknownRecords > 0 && (
                <p className="sbx-hint">{t('sandbox.unknown_records', { count: decoded.unknownRecords })}</p>
            )}

            {openPanel && decoded.state !== null && (
                <SandboxOptionsPanel
                    state={decoded.state}
                    disabled={disabled === true}
                    onPick={pick}
                    onResetAll={() => onChange(encodeSandbox(sandboxDefaults()))}
                />
            )}
        </div>
    );
}
