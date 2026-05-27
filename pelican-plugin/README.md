# Peregrine API Whitelist — Pelican plugin

A small [Pelican panel](https://pelican.dev) plugin that **exempts trusted Peregrine proxy IPs/hostnames from Pelican's Client & Application API rate limits.**

> This is a **Pelican** plugin (it installs into your *Pelican* panel), shipped here because it's part of the Peregrine ↔ Pelican integration. It is **not** a Peregrine plugin.

## Why you need it

Peregrine fronts **many end-users behind a single Pelican API key** (one client key + one admin key). Pelican rate-limits its APIs **per user-UUID (or IP)**:

| Pelican API | Default limit |
|---|---|
| Client API (`/api/client`) | **120 req/min** |
| Application API (`/api/application`) | **240 req/min** |

Because *all* of Peregrine's traffic shares that one key, it all lands in **one bucket** — so at scale Peregrine gets throttled (`429`) even though **no individual end-user is abusive**. Peregrine already enforces its *own* per-user rate limits, so its aggregate proxy traffic should not be capped by a per-key limit meant for individual API users.

This plugin fixes that: requests coming **from your trusted Peregrine host(s)** get `Limit::none()` (unlimited), while **everyone else keeps Pelican's normal limits**. You keep the defaults for the world and let Peregrine do the per-user policing.

> Real-world A/B test: from a whitelisted IP, **500 requests in ~10 s → 0 throttling**. With the plugin disabled, the same spam **blocked almost immediately**. ✅

## What it does (technically)

On `app booted` (so it runs *after* Pelican's core `RouteServiceProvider`), it re-registers the `api.client` and `api.application` rate limiters:

```php
// trusted Peregrine IP → unlimited; everyone else → the normal config limit
return $this->isTrusted($request->ip())
    ? Limit::none()
    : Limit::perMinutes(config("http.rate_limit.{$type}_period"), config("http.rate_limit.{$type}"))
        ->by($request->user()?->uuid ?: $request->ip());
```

- **CIDR supported** (via Symfony `IpUtils`), e.g. `10.0.0.0/24`.
- **Hostnames supported** — resolved to IPs once at boot.
- It only touches the two **global** API limiters. The per-server resource limits (e.g. the `/websocket` token endpoint, ~5–10/min/server) are **untouched** — Peregrine already caches those per server.

## Install

**Option A — Admin panel (recommended):** Pelican admin → *Plugins* → *Upload* → select `peregrine-whitelist.zip`, then enable it.

**Option B — CLI:** copy the `peregrine-whitelist/` folder into your panel's `plugins/` directory, then:

```bash
php artisan p:plugin:install peregrine-whitelist
```

## Configure

Set the trusted source(s) — either in your Pelican `.env`:

```env
PEREGRINE_WHITELIST_IPS="172.16.1.84,172.16.1.30"   # comma-separated, CIDR ok
# or by hostname (resolved at boot):
PEREGRINE_WHITELIST_HOSTS="peregrine.example.com"
```

…or via the plugin's **settings page** in the admin panel. Then apply:

```bash
php artisan config:clear   # (+ restart Octane if you use it)
```

> **Which IP?** Whitelist the IP **Pelican actually sees** as the request source for Peregrine's calls (`request()->ip()`). If Pelican is behind a reverse proxy / load balancer, configure Pelican's trusted proxies first, otherwise you'll see the proxy's IP.

## Security notes

- Any request from a whitelisted IP gets **unlimited** API access — only whitelist IP(s) used **exclusively** by Peregrine.
- Peregrine remains the gatekeeper: it enforces per-user throttles (`throttle:api` 60/min/user, `server-actions`, `pelican-proxy`, file ops) so a single end-user still can't run away.
- Prefer explicit host IPs over a broad CIDR.

## Compatibility

- Pelican panel (Laravel 12+). Uses the standard plugin contract (`plugin.json` + auto-discovered `src/Providers/*ServiceProvider`).

## Contents

```
peregrine-whitelist/
├── plugin.json
├── config/peregrine-whitelist.php
├── src/PeregrineWhitelistPlugin.php              # Filament plugin + admin settings form
├── src/Providers/PeregrineWhitelistServiceProvider.php   # the rate-limiter override
└── lang/{en,fr}/peregrine-whitelist.php
```

`peregrine-whitelist.zip` in this folder is the ready-to-upload artifact (named so Pelican derives the plugin id from the filename).
