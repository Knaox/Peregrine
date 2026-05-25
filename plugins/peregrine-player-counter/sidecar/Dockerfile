# Peregrine game-query sidecar — tiny Node image wrapping GameDig.
# Built by docker-compose (service "game-query", see the plugin's setup guide).
# Loopback-only on the compose network; do NOT publish its port to the host.
FROM node:22-alpine

WORKDIR /app

# Install deps first so the layer is cached unless package.json changes.
COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY index.mjs ./

# Bind on all interfaces so sibling containers reach it by service name.
ENV GAME_QUERY_HOST=0.0.0.0 \
    GAME_QUERY_PORT=9899

EXPOSE 9899

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1:9899/healthz || exit 1

CMD ["node", "index.mjs"]
