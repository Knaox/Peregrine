import { type ReactNode } from 'react';

export interface IconButtonProps {
    icon: ReactNode;
    onClick?: () => void;
    disabled?: boolean;
    isLoading?: boolean;
    variant?: 'ghost' | 'danger';
    size?: 'sm' | 'md';
    title?: string;
    className?: string;
}
