export interface StartupCommandOption {
    name: string;
    command: string;
}

export interface StartupCommandData {
    /** The command currently active on the server (raw, with {{VARS}}). */
    current: string;
    /** Name of the matching egg command, or null when admin-customized. */
    current_name: string | null;
    /** True when the current command is not one of the egg's named commands. */
    is_custom: boolean;
    /** Egg-defined named commands, in the egg's declared order. */
    options: StartupCommandOption[];
}
