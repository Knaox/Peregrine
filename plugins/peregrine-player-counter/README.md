# Player Counter

Official Peregrine plugin that shows the **live number of connected players**
(and up to 5 player names) on each server's overview page.

It reliably reads **six games** (counts come from a small self-hosted **GameDig
sidecar** shipped under [`sidecar/`](sidecar/), except the RCON games). **Which
servers show the card is decided by an egg whitelist** — any other game still
gets a card with a best-effort A2S probe:

- **Minecraft** (Java + Bedrock) — via the sidecar, instant.
- **Valheim** — via the sidecar (A2S).
- **7 Days to Die** — via the sidecar (A2S).
- **ARK: Survival Ascended** and **ARK: Survival Evolved** — via **RCON**
  (`ListPlayers`): ASA's EOS query is 403'd by Epic and ASE's A2S sits on an
  unreachable query port, so RCON is reliable. Needs RCON + an admin password.
- **Palworld** — via **RCON** (`ShowPlayers`); it exposes no A2S. Needs
  `RCONEnabled=True` + an `AdminPassword`.

For the RCON games, a one-click **Resolve RCON** button allocates the RCON port
when it isn't reachable. The six games above appear as an **informational
notice** on the settings page; the **egg whitelist** (settings → *Visibility*)
decides which servers actually show the card — **empty = every egg**, otherwise
only the listed eggs.

The mapping lives in [`config/game-query.php`](config/game-query.php); counts are
cached server-side (Redis). Any egg without a dedicated rule uses a generic A2S
probe (`fallback_type`, default `protocol-valve`) so the card always shows and
attempts a count — set `GAME_QUERY_FALLBACK_TYPE=` (empty) to mark unmapped games
unqueryable instead.

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
