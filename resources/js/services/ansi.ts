/**
 * Strip ANSI / VT escape sequences from a console line so it renders as plain
 * text in the (non-emulator) terminal view fed by the Wings WebSocket.
 *
 * The previous strip regex `\x1b\[[0-9;]*[a-zA-Z]` only matched plain CSI
 * colour / cursor codes. It missed the PRIVATE-mode forms `ESC[?25l` (hide
 * cursor) / `ESC[?25h` (show cursor) — the `?` right after `[` is not in
 * `[0-9;]` — so Unity / SteamWorks dedicated servers (Sons of the Forest,
 * etc.) leaked literal "[?25l" / "[?25h" into the console on every spinner
 * tick. xterm.js (the Pelican panel terminal) interprets these natively ; our
 * plain-text log view must simply drop them.
 *
 * Grammar covered (ECMA-48) :
 *  - CSI : ESC `[` <params 0x30-0x3F, incl. `?` `=` `>` `<`> <intermediates
 *          0x20-0x2F> <final 0x40-0x7E>   ← the param class is the bug fix
 *  - OSC : ESC `]` … terminated by BEL (0x07) or ST (ESC `\`)
 *  - Lone Fe escapes : ESC <0x40-0x5F> (e.g. ESC M)
 */
// eslint-disable-next-line no-control-regex
const ANSI_PATTERN = /\x1b\[[0-?]*[ -/]*[@-~]|\x1b\][^\x07]*(?:\x07|\x1b\\)|\x1b[@-_]/g;

export function stripAnsi(text: string): string {
    return text.replace(ANSI_PATTERN, '');
}
