export interface StatusDotProps {
    status: 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';
    size?: 'sm' | 'md';
    pulse?: boolean;
}
