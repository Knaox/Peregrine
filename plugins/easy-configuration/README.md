# Easy Configuration

A Peregrine plugin that adds a **Game configuration** section to a server's
overview page, letting players edit their config files (`server.properties`,
`GameUserSettings.ini`, `config.yml`, …) through a clean, Nitrado-inspired UI —
sliders, toggles, dropdowns — instead of editing raw text.

> Key principle: a **template is a pure render schema**. It describes *how* to
> display each parameter; it never stores a value. Values live only in the real
> file on the server, read live on display and written back on save via
> Pelican's File Manager API.

## Features

### 1. Configuration editing
A spacious card per parameter (label, discreet description + tooltip, control on
the right), grouped into collapsible sections for INI/TOML. Values are read live
from the real file; parameters present in the file but absent from the template
are auto-detected (`true/false` → boolean, numeric → number, else text). The
section is locked behind a *stop the server* overlay while it runs. Saves are
atomic across files via a floating glass save bar (`Cmd/Ctrl+S`), with
non-blocking soft-revert on invalid input.

### 2. Copy
Copy a configuration to other servers of the same egg you own, via a multi-step
dialog (pick targets — running ones disabled; choose files/parameters; preview;
live recap). Runs as a background job; per-server success/failure is reported.

### 3. Boost
Schedule a temporary multiplication of selected numeric values over a date
window. Servers are stopped and restarted cleanly to apply and to restore. A
value is capped to the lower of the per-parameter `max_cap` and the template
`max`. One boost per parameter; editing the baseline during an active boost
re-applies the capped value and is restored when the boost ends.

## Lossless round-trip
Reading uses a typed parser; **writing substitutes only the changed value token
in the original text**, preserving comments, ordering, whitespace and untouched
lines. Supported formats: `properties`, `ini`, `yaml`, `json`, `toml`.

## Admin guide — templates

Templates are JSON files under `storage/app/easy-config/templates/{id}.json`,
designed to be shared and forked on GitHub. Manage them from
**/admin/plugins → Easy Configuration → Configure** (`/plugins/easy-configuration/manage`),
which is admin-only:

- **List** every template with validity, target eggs, file count and boost flag.
- **Editor**: metadata (id, FR/EN name & description, author), an egg picker
  (with each egg's banner image), a boost toggle + parameter blacklist, and a
  JSON editor for the files/parameters — with a live **Preview** tab that renders
  the template exactly as a player will see it.
- **Import** a `.json` (paste) and **Export** any template.

After dropping templates straight onto disk, run `php artisan easy-config:sync-templates`
to refresh the cache (the admin UI re-syncs automatically on every change).

### Template JSON

See `schema/easy-config-template.v1.json` (JSON Schema) and the ready-to-fork
examples in `samples/` (Minecraft Vanilla, ARK, Paper, Rust). Minimal shape:

```json
{
  "id": "minecraft-vanilla",
  "version": "1.0.0",
  "name": { "fr": "Minecraft Vanilla", "en": "Minecraft Vanilla" },
  "target_eggs": [3, 7],
  "boost": { "enabled": true, "parameter_blacklist": ["server-port"] },
  "files": [
    {
      "id": "server-properties",
      "path": "server.properties",
      "format": "properties",
      "label": { "fr": "Propriétés", "en": "Properties" },
      "parameters": {
        "max-players": {
          "display_type": "slider",
          "config": { "min": 1, "max": 100, "step": 1, "default": 20 },
          "label": { "fr": "Joueurs max", "en": "Max players" }
        }
      }
    }
  ]
}
```

`target_eggs` are local Peregrine egg ids — set them to your eggs (the samples
ship with `[]`). For INI/TOML, nest parameters as `{ "Section": { "key": {…} } }`
and optionally restrict rendered sections with `section_whitelist`.

### Display types

| Type | Config | Boostable |
|---|---|---|
| `boolean` | `true_value`, `false_value` | no |
| `slider` | `min`, `max`, `step`, `suffix` | **yes** |
| `select` | `options[]` (value + FR/EN label) | no |
| `multiselect` | `options[]`, `separator` | no |
| `text` | `regex?`, `max_length?` | no |
| `number` | `min?`, `max?`, `step?`, `float` | **yes** |
| `textarea` | `max_length?` | no |
| `color` | `format` (hex) | no |

## User guide
Open a server → **Game configuration**. Stop the server if prompted, adjust the
controls, then **Save** (or `Cmd/Ctrl+S`). Use **Copy configuration** to clone
settings to your other servers, and toggle **Boost scheduling** to plan
temporary multipliers.

## Permissions
When the Invitations plugin is active, two subuser permissions gate the section:
`easyconfig.read` (view) and `easyconfig.write` (edit). Owners and admins always
have access; without Invitations the plugin falls back to the core `file.read` /
`file.update` permissions. Template management requires `is_admin`.

## Development
- Backend tests: `php vendor/phpunit/phpunit/phpunit -c plugins/easy-configuration/tests/phpunit.xml.dist`
- Frontend type-check: `npx tsc -p plugins/easy-configuration/frontend/tsconfig.json --noEmit`
- Frontend build: `PLUGIN=easy-configuration pnpm run build:plugin`
