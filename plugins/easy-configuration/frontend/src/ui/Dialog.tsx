import clsx from 'clsx';
import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { IconButton } from './Button';

interface DialogProps {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    /** `xl` fills the viewport (file-editor-style overlay) with its own scrolling body. */
    size?: 'md' | 'lg' | 'xl';
    closeLabel: string;
}

/**
 * Modal dialog. The fixed-position scrim is PORTALED to document.body so it
 * really covers the viewport: rendered inline, any ancestor with a transform /
 * filter / backdrop-filter (glass cards, motion wrappers on host pages)
 * becomes the containing block for `position: fixed` and traps the overlay
 * inside that box. Older shells whose bridge lacks `createPortal` fall back
 * to the inline render. The scrim carries `ec-root` so the plugin's scoped
 * styles still apply once detached from the plugin's DOM subtree. Closes on
 * Escape or a click on the backdrop.
 */
export function Dialog({ open, onClose, title, children, footer, size = 'md', closeLabel }: DialogProps) {
    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    const overlay = (
        <div
            className="ec-scrim ec-root"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div className={clsx('ec-dialog', size === 'lg' && 'ec-dialog-lg', size === 'xl' && 'ec-dialog-xl')} role="dialog" aria-modal="true">
                <div className="ec-dialog-head">
                    <p className="ec-dialog-title ec-grow">{title}</p>
                    <IconButton label={closeLabel} onClick={onClose}>
                        <X size={16} />
                    </IconButton>
                </div>
                {children}
                {footer !== undefined && <div className="ec-dialog-foot">{footer}</div>}
            </div>
        </div>
    );

    return typeof createPortal === 'function' ? createPortal(overlay, document.body) : overlay;
}
