# game-query sidecar

A small Node service that wraps [GameDig](https://github.com/gamedig/node-gamedig)
so the **Player Counter** plugin can read live connected-player counts: Steam/
Source (A2S), Minecraft (Java + Bedrock), EOS games (ARK: Survival Ascended via
the Epic API), Hytale (via the `hytale-plugin-query` server mod), and more.

The panel talks to it over HTTP and caches each answer; the sidecar reaches the
game servers (and, for EOS, `api.epicgames.dev`). The full bilingual setup guide
is in the plugin's admin page (**Plugins → Player Counter → Manage → Docker setup
guide**) — including a ready-to-paste docker-compose snippet.

## Run it

```bash
# Docker (recommended) — see the guide for the compose snippet:
docker compose up -d --build game-query

# Bare-metal:
npm install
node index.mjs            # listens on http://127.0.0.1:9899
```

Then set the **Sidecar URL** in the plugin settings (`http://game-query:9899`
under Docker, `http://127.0.0.1:9899` bare-metal) and enable the plugin.

## API

| Method | Path       | Body / Result |
| ------ | ---------- | ------------- |
| `GET`  | `/healthz` | `{ "ok": true }` |
| `POST` | `/query`   | in: `{ "type": "minecraft", "host": "1.2.3.4", "port": 25565, "family": "minecraft" }` → out: `{ "ok": true, "online": 7, "max": 20, "name": "...", "players": ["..."] }` |

## Environment

| Var | Default | Notes |
| --- | --- | --- |
| `GAME_QUERY_PORT` | `9899` | Listen port |
| `GAME_QUERY_HOST` | `127.0.0.1` | Bind address (`0.0.0.0` in Docker) |
| `GAME_QUERY_TOKEN` | _(none)_ | If set, requires `Authorization: Bearer <token>` |
| `GAME_QUERY_TIMEOUT_MS` | `5000` | Per-query timeout |
| `GAME_QUERY_EOS_TIMEOUT_MS` | `6000` | Per-query timeout for EOS (Epic API) |
| `GAME_QUERY_MAX_NAMES` | `5` | Max player names returned |

## systemd (bare-metal production)

```ini
[Unit]
Description=Peregrine game-query sidecar
After=network.target

[Service]
WorkingDirectory=/var/www/peregrine/plugins/peregrine-player-counter/sidecar
ExecStart=/usr/bin/node index.mjs
Restart=always
RestartSec=3
User=www-data

[Install]
WantedBy=multi-user.target
```
