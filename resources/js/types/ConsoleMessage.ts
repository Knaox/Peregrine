export interface ConsoleMessage {
    id: number;
    text: string;
    timestamp: number;
}

export interface WebSocketEvent {
    event: string;
    args: string[];
}
