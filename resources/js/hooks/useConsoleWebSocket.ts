import { useState, useRef, useCallback, useEffect } from 'react';
import type { ConsoleMessage, WebSocketEvent } from '@/types/ConsoleMessage';
import { fetchWebSocketCredentials } from '@/services/serverApi';
import { useWsRetryState } from '@/hooks/useWsRetryState';

const MAX_MESSAGES = 1000;
const ANSI_REGEX = /\x1b\[[0-9;]*[a-zA-Z]/g;

export function useConsoleWebSocket(serverId: number) {
    const [messages, setMessages] = useState<ConsoleMessage[]>([]);
    const [isConnected, setIsConnected] = useState(false);
    const [serverState, setServerState] = useState<string | null>(null);
    const [isGaveUp, setIsGaveUp] = useState(false);

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const messageIdRef = useRef(0);
    const mountedRef = useRef(true);
    const retry = useWsRetryState();

    const clearMessages = useCallback(() => {
        setMessages([]);
    }, []);

    const sendWsCommand = useCallback((command: string) => {
        const ws = wsRef.current;
        if (!ws || ws.readyState !== WebSocket.OPEN) return;

        const payload: WebSocketEvent = {
            event: 'send command',
            args: [command],
        };
        ws.send(JSON.stringify(payload));
    }, []);

    const addMessage = useCallback((text: string) => {
        const cleaned = text.replace(ANSI_REGEX, '');
        const id = ++messageIdRef.current;
        const msg: ConsoleMessage = { id, text: cleaned, timestamp: Date.now() };

        setMessages((prev) => {
            const next = [...prev, msg];
            if (next.length > MAX_MESSAGES) {
                return next.slice(next.length - MAX_MESSAGES);
            }
            return next;
        });
    }, []);

    const scheduleReconnect = useCallback((signal: Parameters<ReturnType<typeof useWsRetryState>['shouldGiveUp']>[0]) => {
        if (retry.shouldGiveUp(signal)) {
            setIsGaveUp(true);
            return;
        }
        if (reconnectTimerRef.current) {
            clearTimeout(reconnectTimerRef.current);
        }
        const delay = retry.nextDelay();
        reconnectTimerRef.current = setTimeout(() => {
            connect();
        }, delay);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const connect = useCallback(async () => {
        if (!mountedRef.current) return;

        let credentials;
        try {
            credentials = await fetchWebSocketCredentials(serverId);
        } catch (error) {
            if (!mountedRef.current) return;
            setIsConnected(false);
            scheduleReconnect({ type: 'credentials_error', error });
            return;
        }

        if (!mountedRef.current) return;

        const ws = new WebSocket(credentials.socket);
        wsRef.current = ws;

        ws.onopen = () => {
            const authPayload: WebSocketEvent = {
                event: 'auth',
                args: [credentials.token],
            };
            ws.send(JSON.stringify(authPayload));
        };

        ws.onmessage = (event: MessageEvent) => {
            if (!mountedRef.current) return;

            let parsed: WebSocketEvent;
            try {
                parsed = JSON.parse(event.data as string) as WebSocketEvent;
            } catch {
                return;
            }

            switch (parsed.event) {
                case 'auth success': {
                    setIsConnected(true);
                    retry.markConnected();
                    const logsPayload: WebSocketEvent = {
                        event: 'send logs',
                        args: [],
                    };
                    ws.send(JSON.stringify(logsPayload));
                    break;
                }
                case 'console output': {
                    for (const line of parsed.args) {
                        addMessage(line);
                    }
                    break;
                }
                case 'status': {
                    if (parsed.args[0]) {
                        setServerState(parsed.args[0]);
                    }
                    break;
                }
                case 'token expiring': {
                    fetchWebSocketCredentials(serverId)
                        .then((creds) => {
                            if (ws.readyState === WebSocket.OPEN) {
                                const reAuthPayload: WebSocketEvent = {
                                    event: 'auth',
                                    args: [creds.token],
                                };
                                ws.send(JSON.stringify(reAuthPayload));
                            }
                        })
                        .catch(() => {});
                    break;
                }
                case 'token expired': {
                    ws.close();
                    break;
                }
            }
        };

        ws.onclose = (event: CloseEvent) => {
            if (!mountedRef.current) return;
            setIsConnected(false);
            scheduleReconnect({ type: 'close', code: event.code });
        };

        ws.onerror = () => {
            if (!mountedRef.current) return;
            setIsConnected(false);
        };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverId, addMessage]);

    useEffect(() => {
        mountedRef.current = true;
        connect();

        return () => {
            mountedRef.current = false;
            if (reconnectTimerRef.current) {
                clearTimeout(reconnectTimerRef.current);
                reconnectTimerRef.current = null;
            }
            if (wsRef.current) {
                wsRef.current.close();
                wsRef.current = null;
            }
        };
    }, [connect]);

    return { messages, isConnected, serverState, sendWsCommand, clearMessages, isGaveUp };
}
