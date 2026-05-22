import clsx from 'clsx';
import { Check, HelpCircle, RotateCcw } from 'lucide-react';
import type { ReactNode } from 'react';
import { pickLabel, useT } from '../lib/i18n';
import type { ConfigParam } from '../types';
import { IconButton } from '../ui/Button';
import { Badge, Tooltip } from '../ui/surfaces';
import { FieldControl } from './FieldControl';

interface FieldRowProps {
    param: ConfigParam;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    dirty?: boolean;
    saved?: boolean;
    invalid?: boolean;
    onReset?: () => void;
    boost?: ReactNode;
}

/**
 * One Nitrado-style parameter card: clear label, discreet description, the
 * interactive control on the right, with dirty/saved indicators, an optional
 * reset-to-default affordance and a slot for the boost badge.
 */
export function FieldRow({ param, value, onChange, disabled, dirty, saved, invalid, onReset, boost }: FieldRowProps) {
    const { t, lang } = useT();
    const label = pickLabel(param.label, lang, param.key);
    const description = pickLabel(param.description, lang, '');

    const defaultValue = param.config.default;
    const canReset =
        onReset !== undefined &&
        defaultValue !== undefined &&
        !disabled &&
        value !== String(defaultValue);

    return (
        <div className={clsx('ec-field', dirty && 'ec-field-dirty')}>
            <div className="ec-field-label-col">
                <span className="ec-field-label">
                    {label}
                    {description !== '' && (
                        <Tooltip content={description}>
                            <span className="ec-help">
                                <HelpCircle size={13} />
                            </span>
                        </Tooltip>
                    )}
                    {param.inferred && <Badge variant="muted">{t('field.auto_detected')}</Badge>}
                </span>
                {description !== '' && <span className="ec-field-desc ec-truncate">{description}</span>}
                <span className="ec-field-desc ec-muted">{param.section ? `${param.section} · ${param.key}` : param.key}</span>
            </div>

            <div className="ec-field-control">
                {boost}
                <FieldControl param={param} value={value} onChange={onChange} disabled={disabled} invalid={invalid} />
                {saved && (
                    <span className="ec-field-saved" aria-hidden>
                        <Check size={15} />
                    </span>
                )}
                {canReset && (
                    <IconButton label={t('field.reset_default')} className="ec-reset" onClick={onReset}>
                        <RotateCcw size={14} />
                    </IconButton>
                )}
            </div>
        </div>
    );
}
