import clsx from 'clsx';
import { Check, HelpCircle, Link2, RotateCcw, Tag } from 'lucide-react';
import type { ReactNode } from 'react';
import { pickLabel, useT } from '../lib/i18n';
import type { ConfigParam } from '../types';
import { IconButton } from '../ui/Button';
import { Badge, Tooltip } from '../ui/surfaces';
import { BoostSelectToggle } from './BoostSelectToggle';
import { FieldControl } from './FieldControl';

interface FieldRowProps {
    param: ConfigParam;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    /** Server running: control stays interactive (to show the lock message) but the reset shortcut is hidden. */
    locked?: boolean;
    dirty?: boolean;
    saved?: boolean;
    invalid?: boolean;
    onReset?: () => void;
    boost?: ReactNode;
    /** Boost selection: render a checkbox to include this param in a boost. */
    boostMode?: boolean;
    boostable?: boolean;
    boostSelected?: boolean;
    /** Already covered by a pending/active boost — checkbox is ticked but locked. */
    boostLocked?: boolean;
    onToggleBoost?: () => void;
    /** Per-parameter divide (deboost) flag + toggle for a ticked boost parameter. */
    boostDivide?: boolean;
    onToggleDivide?: () => void;
    /** Admin only: annotate this discovered (inferred) parameter into the template. */
    onAnnotate?: () => void;
}

/**
 * One Nitrado-style parameter card: clear label, discreet description, the
 * interactive control on the right, with dirty/saved indicators, an optional
 * reset-to-default affordance and a slot for the boost badge.
 */
export function FieldRow({
    param,
    value,
    onChange,
    disabled,
    locked,
    dirty,
    saved,
    invalid,
    onReset,
    boost,
    boostMode,
    boostable,
    boostSelected,
    boostLocked,
    onToggleBoost,
    boostDivide,
    onToggleDivide,
    onAnnotate,
}: FieldRowProps) {
    const { t, lang } = useT();
    const label = pickLabel(param.label, lang, param.key);
    const description = pickLabel(param.description, lang, '');

    const defaultValue = param.config.default;
    const canReset =
        onReset !== undefined &&
        defaultValue !== undefined &&
        !disabled &&
        !locked &&
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
                    {param.env_var && (
                        <Tooltip content={t('field.env_linked', { name: param.env_var })}>
                            <span className="ec-help">
                                <Badge variant="info">
                                    <Link2 size={11} /> {param.env_var}
                                </Badge>
                            </span>
                        </Tooltip>
                    )}
                </span>
                {description !== '' && <span className="ec-field-desc ec-truncate">{description}</span>}
                <span className="ec-field-desc ec-muted">
                    {param.section ? `${param.section} · ${param.key}` : param.key}
                    {param.occurrence ? ` #${param.occurrence + 1}` : ''}
                </span>
            </div>

            <div className="ec-field-control">
                {boostMode && boostable && (
                    <BoostSelectToggle
                        selected={boostSelected ?? false}
                        locked={boostLocked ?? false}
                        divide={boostDivide ?? false}
                        onToggle={onToggleBoost ?? (() => {})}
                        onToggleDivide={onToggleDivide ?? (() => {})}
                    />
                )}
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
                {param.inferred && onAnnotate && (
                    <Tooltip content={t('annotate.button')}>
                        <span>
                            <IconButton label={t('annotate.button')} onClick={onAnnotate}>
                                <Tag size={14} />
                            </IconButton>
                        </span>
                    </Tooltip>
                )}
            </div>
        </div>
    );
}
