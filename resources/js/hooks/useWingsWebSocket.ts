import { useState, useRef, useCallback, useEffect } from 'react';
import { fetchWebSocketCredentials } from '@/services/serverApi';
import { useWsRetryState, type WsFailure } from '@/hooks/useWsRetryState';
import { detectMinecraftIssue } from '@/services/minecraftConsole';
import { stripAnsi } from '@/services/ansi';
import type { ServerResources } from '@/types/ServerResources';
import type { ConsoleMessage } from '@/types/ConsoleMessage';

const MAX_MESSAGES = 1000;
const KEEPALIVE_INTERVAL = 10_000;

interface WsEvent {
    event: string;
    args: string[];
}

interface UseWingsWebSocketOptions {
    /** Subscribe to console output events */
    console?: boolean;
    /** Subscribe to stats events */
    stats?: boolean;
}

interface UseWingsWebSocketReturn {
    messages: ConsoleMessage[];
    resources: ServerResources | undefined;
    serverState: string;
    isConnected: boolean;
    /** True once the retry policy has given up (rate-limited or permission-denied). */
    isGaveUp: boolean;
    /** True once Wings has emitted `install completed` since this hook mounted. */
    installCompleted: boolean;
    /** The Minecraft server logged "you must accept the EULA" and won't boot. */
    eulaRequired: boolean;
    /** The server failed to boot on an incompatible Java version. */
    javaIssue: { detected: boolean; requiredJava: number | null };
    /** Rolling buffer of the last 1000 log lines — survives the offline clear. */
    history: ConsoleMessage[];
    sendCommand: (command: string) => void;
    clearMessages: () => void;
}

export function useWingsWebSocket(
    serverId: number,
    options: UseWingsWebSocketOptions = { stats: true },
): UseWingsWebSocketReturn {
    const [messages, setMessages] = useState<ConsoleMessage[]>([]);
    const [history, setHistory] = useState<ConsoleMessage[]>([]);
    const [resources, setResources] = useState<ServerResources | undefined>(undefined);
    const [serverState, setServerState] = useState<string>('offline');
    const [isConnected, setIsConnected] = useState(false);
    const [isGaveUp, setIsGaveUp] = useState(false);
    const [installCompleted, setInstallCompleted] = useState(false);
    const [eulaRequired, setEulaRequired] = useState(false);
    const [javaIssue, setJavaIssue] = useState<{ detected: boolean; requiredJava: number | null }>({
        detected: false,
        requiredJava: null,
    });

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const keepaliveTimer = useRef<ReturnType<typeof setInterval> | null>(null);
    const msgId = useRef(0);
    const alive = useRef(false);
    // Previous network sample (cumulative bytes + receipt time), used to derive
    // the live throughput rate from the delta against the next sample.
    const netSampleRef = useRef<{ rx: number; tx: number; t: number } | null>(null);
    // True while the server is offline/stopped — freezes the live `messages`
    // buffer (so a reconnect's re-sent logs don't repopulate a console the user
    // expects to be cleared) while `history` keeps accumulating.
    const offline = useRef(false);
    // Wings keeps reporting power-state `offline` during an egg (re)install, so
    // the offline freeze must be lifted while install frames stream ('active')
    // and the install log preserved after 'install completed' ('done') until
    // the server has been seen active again.
    const installPhase = useRef<'none' | 'active' | 'done'>('none');
    const retry = useWsRetryState();

    const clearMessages = useCallback(() => setMessages([]), []);

    const resetIssues = useCallback(() => {
        setEulaRequired(false);
        setJavaIssue({ detected: false, requiredJava: null });
    }, []);

    // Pattern-match each incoming log line for a fixable Minecraft boot
    // failure (EULA / Java). Cheap enough to run on every line.
    const scanForIssues = useCallback((line: string) => {
        const issue = detectMinecraftIssue(line);
        if (!issue) return;
        if (issue.type === 'eula') {
            setEulaRequired(true);
        } else {
            setJavaIssue({ detected: true, requiredJava: issue.requiredJava });
        }
    }, []);

    // Record a log line: always into `history` (rolling 1000, kept across the
    // offline clear), and into the live `messages` only while not frozen.
    const record = useCallback((text: string) => {
        const entry = { id: ++msgId.current, text, timestamp: Date.now() };
        setHistory((prev) => {
            const next = [...prev, entry];
            return next.length > MAX_MESSAGES ? next.slice(-MAX_MESSAGES) : next;
        });
        if (!offline.current) {
            setMessages((prev) => {
                const next = [...prev, entry];
                return next.length > MAX_MESSAGES ? next.slice(-MAX_MESSAGES) : next;
            });
        }
    }, []);

    // Clear the live console the instant the server goes offline (so it shows
    // the "server is offline" placeholder) and freeze it until it comes back.
    // Exception: while an install is streaming, the power-state stays `offline`
    // but the console must keep flowing; right after an install, the log is
    // kept on screen instead of being cleared by the still-offline state.
    const updateOfflineFreeze = useCallback((state: string) => {
        if (installPhase.current === 'active') {
            offline.current = false;
            return;
        }
        const isOff = state === 'offline' || state === 'stopped';
        if (!isOff && installPhase.current === 'done') {
            installPhase.current = 'none';
        }
        if (isOff && !offline.current) {
            offline.current = true;
            if (installPhase.current !== 'done') {
                setMessages([]);
            }
        } else if (!isOff && offline.current) {
            offline.current = false;
        }
    }, []);

    const sendCommand = useCallback((command: string) => {
        const ws = wsRef.current;
        if (ws?.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ event: 'send command', args: [command] }));
        }
    }, []);

    const cleanup = useCallback(() => {
        if (reconnectTimer.current) {
            clearTimeout(reconnectTimer.current);
            reconnectTimer.current = null;
        }
        if (keepaliveTimer.current) {
            clearInterval(keepaliveTimer.current);
            keepaliveTimer.current = null;
        }
        const ws = wsRef.current;
        if (ws) {
            ws.onopen = null;
            ws.onmessage = null;
            ws.onclose = null;
            ws.onerror = null;
            if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
                ws.close();
            }
            wsRef.current = null;
        }
    }, []);

    const scheduleReconnect = useCallback((signal: WsFailure) => {
        if (!alive.current) return;
        if (retry.shouldGiveUp(signal)) {
            setIsGaveUp(true);
            return;
        }
        const delay = retry.nextDelay();
        reconnectTimer.current = setTimeout(() => {
            void connect();
        }, delay);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const startKeepalive = useCallback((ws: WebSocket) => {
        if (keepaliveTimer.current) clearInterval(keepaliveTimer.current);
        keepaliveTimer.current = setInterval(() => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ event: 'send stats', args: [] }));
            }
        }, KEEPALIVE_INTERVAL);
    }, []);

    const connect = useCallback(async () => {
        if (!alive.current || serverId <= 0) return;
        cleanup();

        let credentials;
        try {
            credentials = await fetchWebSocketCredentials(serverId);
        } catch (error) {
            if (alive.current) scheduleReconnect({ type: 'credentials_error', error });
            return;
        }

        if (!alive.current) return;

        const ws = new WebSocket(credentials.socket);
        wsRef.current = ws;

        ws.onopen = () => {
            ws.send(JSON.stringify({ event: 'auth', args: [credentials.token] }));
        };

        ws.onmessage = (evt: MessageEvent) => {
            if (!alive.current) return;
            let data: WsEvent;
            try {
                data = JSON.parse(evt.data as string) as WsEvent;
            } catch {
                return;
            }

            switch (data.event) {
                case 'auth success':
                    setIsConnected(true);
                    retry.markConnected();
                    startKeepalive(ws);
                    // Request initial data
                    ws.send(JSON.stringify({ event: 'send stats', args: [] }));
                    if (options.console) {
                        ws.send(JSON.stringify({ event: 'send logs', args: [] }));
                    }
                    break;

                case 'console output':
                    if (options.console && data.args[0] !== undefined) {
                        const cleaned = stripAnsi(data.args[0]);
                        scanForIssues(cleaned);
                        record(cleaned);
                    }
                    break;

                // Wings emits `install output` while the egg install script
                // runs, but keeps reporting the power-state as `offline` the
                // whole time — so these frames must lift the offline freeze
                // themselves or they would only ever land in `history`. Lines
                // are tagged with [install] for visual distinction.
                case 'install output':
                    if (options.console && data.args[0] !== undefined) {
                        installPhase.current = 'active';
                        offline.current = false;
                        const cleaned = stripAnsi(data.args[0]);
                        scanForIssues(cleaned);
                        record(`[install] ${cleaned}`);
                    }
                    break;

                case 'install started':
                    installPhase.current = 'active';
                    offline.current = false;
                    if (options.console) {
                        record('[Peregrine] Installation starting…');
                    }
                    // A fresh install attempt clears any stale boot-failure state.
                    resetIssues();
                    setInstallCompleted(false);
                    break;

                case 'install completed':
                    if (options.console) {
                        record('[Peregrine] Installation completed.');
                    }
                    // Keep the install log visible: the server is still offline
                    // right after an install, and the freeze would clear it.
                    installPhase.current = 'done';
                    setInstallCompleted(true);
                    break;

                case 'status':
                    if (data.args[0]) {
                        const state = data.args[0];
                        setServerState(state);
                        // Server recovered — any prior boot-failure prompt is moot.
                        if (state === 'running') resetIssues();
                        // Clear/freeze the live console when the server goes down.
                        updateOfflineFreeze(state);
                        // Re-broadcast the live power state so any plugin bundle
                        // can react (e.g. lock an editor) the instant the server
                        // starts/stops, reusing this single Wings connection
                        // instead of opening its own.
                        window.dispatchEvent(new CustomEvent('peregrine:server-power', { detail: { serverId, state } }));
                    }
                    break;

                case 'stats':
                    if (data.args[0]) {
                        try {
                            const s = JSON.parse(data.args[0]) as Record<string, unknown>;
                            const net = s.network as Record<string, number> | undefined;
                            const rx = net?.rx_bytes ?? 0;
                            const tx = net?.tx_bytes ?? 0;
                            // Derive the live up/down speed from the delta since the
                            // previous sample. Wings reports cumulative totals, so a
                            // rate needs two points. Clamp negatives (a counter reset
                            // on restart) to 0 to avoid a bogus spike.
                            const now = performance.now();
                            const prev = netSampleRef.current;
                            let rxRate = 0;
                            let txRate = 0;
                            if (prev) {
                                const dt = (now - prev.t) / 1000;
                                if (dt > 0) {
                                    rxRate = Math.max(0, (rx - prev.rx) / dt);
                                    txRate = Math.max(0, (tx - prev.tx) / dt);
                                }
                            }
                            netSampleRef.current = { rx, tx, t: now };
                            setResources({
                                state: (s.state as string) ?? 'offline',
                                cpu: (s.cpu_absolute as number) ?? 0,
                                memory_bytes: (s.memory_bytes as number) ?? 0,
                                disk_bytes: (s.disk_bytes as number) ?? 0,
                                network_rx: rx,
                                network_tx: tx,
                                network_rx_rate: rxRate,
                                network_tx_rate: txRate,
                                uptime: (s.uptime as number) ?? 0,
                            });
                            if (s.state) {
                                setServerState(s.state as string);
                                if (s.state === 'running') resetIssues();
                                updateOfflineFreeze(s.state as string);
                                window.dispatchEvent(new CustomEvent('peregrine:server-power', { detail: { serverId, state: s.state } }));
                            }
                        } catch { /* ignore */ }
                    }
                    break;

                case 'token expiring':
                    // Force-bypass Peregrine's 5 min creds cache here :
                    // the cached JWT is, by construction, almost as old
                    // as the live one Wings is now warning about, so
                    // serving it again would fail seconds later. The
                    // `?fresh=1` query param tells the controller to
                    // round-trip to Pelican Panel for a brand-new
                    // signed JWT and replace the cached entry.
                    fetchWebSocketCredentials(serverId, { force: true })
                        .then((creds) => {
                            if (ws.readyState === WebSocket.OPEN) {
                                ws.send(JSON.stringify({ event: 'auth', args: [creds.token] }));
                            }
                        })
                        .catch(() => { /* will get token expired next */ });
                    break;

                case 'token expired':
                case 'jwt error':
                    ws.close();
                    break;

                case 'daemon error':
                    if (options.console && data.args[0]) {
                        scanForIssues(data.args[0]);
                        record(`[ERROR] ${data.args[0]}`);
                    }
                    break;
            }
        };

        ws.onclose = (event: CloseEvent) => {
            setIsConnected(false);
            wsRef.current = null;
            if (keepaliveTimer.current) {
                clearInterval(keepaliveTimer.current);
                keepaliveTimer.current = null;
            }
            if (alive.current) scheduleReconnect({ type: 'close', code: event.code });
        };

        ws.onerror = () => {
            // onclose will fire after onerror
        };
    }, [serverId, options.console, options.stats, cleanup, scheduleReconnect, startKeepalive, scanForIssues, resetIssues, record, updateOfflineFreeze]);

    useEffect(() => {
        alive.current = true;
        // Small delay to survive React StrictMode's mount/unmount/remount cycle in dev
        const initTimer = setTimeout(() => {
            if (alive.current) void connect();
        }, 100);
        return () => {
            alive.current = false;
            clearTimeout(initTimer);
            cleanup();
        };
    }, [connect, cleanup]);

    return { messages, history, resources, serverState, isConnected, isGaveUp, installCompleted, eulaRequired, javaIssue, sendCommand, clearMessages };
}
