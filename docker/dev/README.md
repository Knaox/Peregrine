# Dev / contributor compose files

These compose files **build the image from source** and are intended for
contributors hacking on Peregrine — not for end users.

| File | Purpose |
|---|---|
| `docker-compose.dev.yml` | Local dev with mounted volumes + hot reload (Vite + xdebug). |
| `docker-compose.prod.yml` | Local production-like build (no GHCR pull, builds from `Dockerfile`). |

End users should use the compose files at the repo root (`docker-compose.yml`
or `docker-compose.external-db.yml`) which pull the published image from
`ghcr.io/knaox/peregrine:latest`.
