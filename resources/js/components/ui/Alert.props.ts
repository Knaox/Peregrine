import { type ReactNode } from 'react';

export interface AlertProps {
    variant: 'error' | 'success' | 'info' | 'warning';
    children: ReactNode;
    className?: string;
}
