import { ReactNode } from 'react';

export interface CardProps {
    hover?: boolean;
    className?: string;
    children: ReactNode;
    onClick?: () => void;
}
