import { useRef } from 'react';
import { ApiError } from '@/services/http';

/**
 * Shared reconnect policy for all server WebSocket hooks (console, stats,
 * wings). Centralises the two failure modes that must stop reconnection:
 *
 *   1. Admin-mode rate limit: Pelican's Client API caps at 60 req/min. Three
 *      hooks on the same server page hammer it into 429 territory fast.
 *   2. Permission denial: Wings close codes in the 4xxx range signal "this
 *      JWT doesn't grant access to this server" (e.g. admin viewing another
 *      user's server when the panel's Pelican client key isn't privileged).
 *
 * In both cases an unbounded retry loop doesn't help — it just feeds the
 * throttle. The caller reads `shouldGiveUp` before scheduling another
 * reconnect; when true, the hook gracefully stops.
 */

const RECONNECT_MAX_DELAY = 30_000;
const MAX_ATTEMPTS = 8;

interface UseWsRetryStateReturn {
    /** Next backoff delay in ms, then doubles internally (capped). */
    nextDelay(): number;
    /** Mark a connection as successful — resets the backoff + attempts. */
    markConnected(): void;
    /**
     * After a fetchWebSocketCredentials() throw or a close event, returns true
     * if further reconnects should be abandoned.
     */
    shouldGiveUp(signal: WsFailure): boolean;
    /** For instrumentation/debug: how many attempts have been exhausted. */
    attempts(): number;
}

export type WsFailure =
    | { type: 'credentials_error'; error: unknown }
    | { type: 'close'; code?: number };

export function useWsRetryState(): UseWsRetryStateReturn {
    const attemptRef = useRef(0);
    const delayRef = useRef(1000);

    return {
        nextDelay() {
            attemptRef.current += 1;
            const delay = delayRef.current;
            delayRef.current = Math.min(delay * 2, RECONNECT_MAX_DELAY);
            return delay;
        },
        markConnected() {
            attemptRef.current = 0;
            delayRef.current = 1000;
        },
        shouldGiveUp(signal) {
            if (attemptRef.current >= MAX_ATTEMPTS) {
                return true;
            }
            if (signal.type === 'credentials_error' && signal.error instanceof ApiError) {
                // 403 / 404 from Peregrine proxy: no access to this server.
                if (signal.error.status === 403 || signal.error.status === 404) {
                    return true;
                }
            }
            if (signal.type === 'close' && signal.code !== undefined) {
                // Wings sends codes in the 4000–4999 range for its own errors
                // (jwt invalid, permission denied, etc). 1006 = abnormal close
                // from network — worth retrying. Anything 4xxx = protocol-level
                // refusal — stop.
                if (signal.code >= 4000 && signal.code < 5000) {
                    return true;
                }
            }
            return false;
        },
        attempts() {
            return attemptRef.current;
        },
    };
}
