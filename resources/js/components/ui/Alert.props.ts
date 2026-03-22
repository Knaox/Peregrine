import { type ReactNode } from 'react';

export interface AlertProps {
    variant: 'error' | 'success' | 'info';
    children: ReactNode;
    className?: string;
}
