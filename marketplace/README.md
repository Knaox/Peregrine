# Peregrine plugin marketplace

Panels fetch the registry from
`https://raw.githubusercontent.com/Knaox/peregrine-plugins/main/registry.json`
(overridable via `MARKETPLACE_REGISTRY_URL`).

## Publishing the "invitations" plugin

The `invitations` plugin ships with Peregrine (see `plugins/invitations/`) and
is auto-activated at setup — marketplace listing is only for re-install.

### Steps

1. In the main repo, tag a release for the plugin:
   ```bash
   git tag invitations-0.8.0
   git push origin invitations-0.8.0
   ```

2. Create the zip asset for the release:
   ```bash
   cd plugins
   zip -r invitations-0.8.0.zip invitations
   gh release create invitations-0.8.0 invitations-0.8.0.zip \
       --title "Invitations 0.8.0" \
       --notes "See plugins/invitations/CHANGELOG.md"
   ```

3. Copy `marketplace/registry.json` from this repo into the root of the
   `Knaox/peregrine-plugins` repo (branch `main`) and push. Panels refresh
   the cache every hour (`CACHE_KEY = marketplace.registry`, TTL 3600s).

## Adding a new plugin to the marketplace

Append an entry to the `plugins` array in `registry.json`:

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "description": "…",
    "author": "…",
    "license": "MIT",
    "download_url": "https://github.com/owner/repo/releases/download/v1.0.0/my-plugin-1.0.0.zip",
    "min_peregrine_version": "1.0.0"
}
```

The `download_url` must return a ZIP containing a single top-level folder
that matches the plugin's `id`, with a `plugin.json` at the root and source
under `src/`.
