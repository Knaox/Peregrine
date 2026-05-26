# Player Counter

Official Peregrine plugin that shows the **live number of connected players**
(and up to 5 player names) on each server's overview page.

It officially supports **six games** (the card appears only on these; every
other egg shows nothing). Counts come from a small self-hosted **GameDig
sidecar** (shipped under [`sidecar/`](sidecar/)), except the RCON games:

- **Minecraft** (Java + Bedrock) — via the sidecar, instant.
- **Valheim** — via the sidecar (A2S).
- **7 Days to Die** — via the sidecar (A2S).
- **ARK: Survival Ascended** and **ARK: Survival Evolved** — via **RCON**
  (`ListPlayers`): ASA's EOS query is 403'd by Epic and ASE's A2S sits on an
  unreachable query port, so RCON is reliable. Needs RCON + an admin password.
- **Palworld** — via **RCON** (`ShowPlayers`); it exposes no A2S. Needs
  `RCONEnabled=True` + an `AdminPassword`.

For the RCON games, a one-click **Resolve RCON** button allocates the RCON port
when it isn't reachable. The supported list is shown as a notice on the settings
page, and an **egg whitelist** (settings → *Visibility*) can narrow the card to
specific eggs among the supported games.

The mapping lives in [`config/game-query.php`](config/game-query.php); counts are
cached server-side (Redis) so polling stays cheap. An opt-in, best-effort generic
Steam probe (`GAME_QUERY_STEAM_FALLBACK=true`) can count other A2S games but is
**off by default** and unsupported.

## Setup

1. Activate the plugin (Admin → Plugins → Player Counter).
2. Open **Manage** → **Docker setup guide** for a copy-paste docker-compose
   snippet to run the sidecar (or run it bare-metal with Node).
3. Set the **Sidecar URL** + enable, then **Test sidecar**.

If the sidecar is down or the plugin is disabled, the widget degrades to an
"offline / hidden" state — nothing breaks.

## Build (frontend bundle)

```bash
PLUGIN=peregrine-player-counter npm run build:plugin
```

Outputs `frontend/dist/bundle.js` (IIFE; React / TanStack Query / react-i18next
are provided by the host shell).
