import { type ReactNode } from 'react';

export interface ModalProps {
    /** Whether the modal is rendered. */
    open: boolean;
    /** Called on backdrop click, Escape, or the close button. */
    onClose: () => void;
    /** Accessible dialog title, rendered in the header. */
    title: string;
    /** Optional leading icon shown in a tinted square beside the title. */
    icon?: ReactNode;
    children: ReactNode;
    /** Optional footer (typically the action buttons), right-aligned. */
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
}
