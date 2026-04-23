export interface StatusDotProps {
    status: 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting' | 'provisioning' | 'provisioning_failed';
    size?: 'sm' | 'md';
    pulse?: boolean;
}
