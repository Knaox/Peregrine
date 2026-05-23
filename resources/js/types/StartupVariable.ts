export interface StartupVariable {
    name: string;
    description: string;
    env_variable: string;
    default_value: string;
    server_value: string;
    is_editable: boolean;
    rules: string;
    /** True when a plugin has claimed/manages this variable (badged as "linked"). */
    claimed?: boolean;
}
