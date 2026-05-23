import clsx from 'clsx';
import { AlertTriangle, Info } from 'lucide-react';
import type { ReactNode } from 'react';

type CalloutVariant = 'warning' | 'info';

/**
 * Prominent inline notice: a tinted, bordered panel with an icon — used where a
 * message must not be missed (e.g. the boost stop/restart warning). Far more
 * visible than discreet `ec-secondary` helper text.
 */
export function Callout({ variant = 'warning', children }: { variant?: CalloutVariant; children: ReactNode }) {
    const Icon = variant === 'warning' ? AlertTriangle : Info;

    return (
        <div className={clsx('ec-callout', `ec-callout-${variant}`)} role="note">
            <Icon size={16} className="ec-callout-icon" aria-hidden />
            <span>{children}</span>
        </div>
    );
}
