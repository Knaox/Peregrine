export interface DatabaseHost {
    address: string;
    port: number;
}

export interface Database {
    id: string;
    name: string;
    host: DatabaseHost | string;
    port?: number;
    username: string;
    password?: string;
    connections_from: string;
    max_connections: number;
}

export function getDatabaseHostString(db: Database): string {
    if (typeof db.host === 'object' && db.host !== null) {
        return `${db.host.address}:${db.host.port}`;
    }
    return db.port ? `${db.host}:${db.port}` : String(db.host);
}

