import { ReactNode } from 'react';

export interface BadgeProps {
    color?: 'green' | 'yellow' | 'red' | 'gray' | 'orange' | 'blue';
    children: ReactNode;
    className?: string;
}
