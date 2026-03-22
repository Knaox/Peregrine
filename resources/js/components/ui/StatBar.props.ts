export interface StatBarProps {
    label: string;
    value: number;
    max: number;
    formatted: string;
    color?: 'green' | 'yellow' | 'red' | 'orange';
}
