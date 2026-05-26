export type PlayerQueryState =
    | 'online'
    | 'offline'
    | 'unsupported'
    | 'unavailable'
    | 'unknown';

/** Live connected-player count — GET /api/plugins/peregrine-player-counter/servers/{id}/players. */
export interface ServerPlayers {
    online: number | null;
    max: number | null;
    state: PlayerQueryState;
    family: string;
    queryable: boolean;
    /** True when this game is counted over RCON (ARK, Palworld) — enables the Resolve RCON helper. */
    rcon: boolean;
    name: string | null;
    players: string[];
    queried_at: string;
}
