import { type ReactNode } from 'react';

export interface ButtonProps {
    variant?: 'primary' | 'danger' | 'ghost' | 'secondary';
    size?: 'sm' | 'md';
    isLoading?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit';
    onClick?: () => void;
    children: ReactNode;
    className?: string;
}
