# Player Counter

Official Peregrine plugin that shows the **live number of connected players**
(and up to 5 player names) on each server's overview page.

Supported via a small self-hosted **GameDig sidecar** (shipped under
[`sidecar/`](sidecar/)):

- **Steam / Source (A2S)** and **Minecraft** (Java + Bedrock) — instant.
- **EOS games** — ARK: Survival Ascended (`asa`), Renown, The Isle Evrima,
  Squad — via the Epic API (works even if the game's query port is firewalled).
- **Hytale** — via the [`hytale-plugin-query`](https://github.com/nitrado/hytale-plugin-query)
  server mod.

The egg → game mapping lives in [`config/game-query.php`](config/game-query.php);
counts are cached server-side (Redis) so polling stays cheap and never hammers
the Epic API.

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
