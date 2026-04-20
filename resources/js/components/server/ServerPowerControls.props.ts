export interface ServerPowerControlsProps {
    serverId: number;
    state: string | undefined;
    canStart?: boolean;
    canStop?: boolean;
    canRestart?: boolean;
}
