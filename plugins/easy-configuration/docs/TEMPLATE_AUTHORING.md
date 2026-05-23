# Easy Configuration — Template authoring guide

This document fully describes the **template JSON format** used by the Easy
Configuration plugin. It is self-contained: paste it (with the example at the
end) into an AI to generate a new template for any game.

> A **template is a pure render schema**. It describes *how* to display each
> parameter of a server's config file(s) — it **never stores a value**. Values
> live only in the real file on the server, read live on display and written
> back on save. JSON Schema: `schema/easy-config-template.v1.json`.

A template is one `<id>.json` file. It is matched to servers by Pelican egg id
(`target_eggs`), so editing a template applies to **every server of those eggs**.

---

## 1. Root object

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `id` | string | ✅ | Unique slug, also the file name. Pattern `^[a-z0-9._-]+$`. |
| `version` | string | ✅ | Semver, e.g. `"1.0.0"`. |
| `name` | localeLabel | ✅ | Display name. |
| `description` | localeLabel | — | Short description. |
| `author` | string | — | Template author. |
| `target_eggs` | integer[] | ✅ | Pelican egg ids this template applies to (`[]` = none). |
| `columns` | 1 \| 2 \| 3 | — | Player editor layout (default 1). |
| `boost` | object | — | `{ "enabled": bool, "parameter_blacklist": string[] }`. |
| `files` | file[] | ✅ | At least one file. |

`localeLabel` = `{ "fr": "...", "en": "..." }` — **at least one** of `fr`/`en`.

`boost`: when `enabled`, players can schedule temporary ×/÷ changes on numeric
(`number`/`slider`) parameters. `parameter_blacklist` lists keys that must never
be boostable (e.g. ports).

---

## 2. File object (`files[]`)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `id` | string | ✅ | Stable id for this file within the template. |
| `path` | string | ✅ | Path on the server, e.g. `server.properties`, `Config/Game.ini`. |
| `format` | enum | ✅ | One of: `properties`, `ini`, `yaml`, `json`, `toml`, `palworld`, `theforest`. |
| `enabled` | boolean | — | Default `true`. |
| `label` | localeLabel | — | File heading. |
| `section_labels` | object | — | Friendly FR/EN names per native section, keyed by raw section name. |
| `section_whitelist` | string[] | — | INI/TOML only: show only these sections (empty/absent = all). |
| `parameters` | object | ✅ | See below. |

**`parameters` shape depends on the format:**

- **Flat** (`properties`, `json`, `yaml`, `theforest`) → `{ "key": parameter }`.
- **Sectioned** (`ini`, `toml`, `palworld`) → `{ "section": { "key": parameter } }`.
  Use the raw section header verbatim, e.g. `"/script/shootergame.shootergamemode"`.

> **Discovery**: any key found in the real file but **absent** from the template
> is auto-detected and shown anyway (raw key, type inferred). An admin can then
> annotate it into the template from the editor. So you only need to declare the
> parameters you want to *curate* (label/type/constraints).

> **Repeated keys** (e.g. ARK `ConfigOverrideItemMaxQuantity`): declare the key
> **once**; every occurrence in the file reuses that definition. Players add more
> copies via "Add parameter"; the writer appends a new occurrence, never
> overwriting an existing one.

---

## 3. Parameter object

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `display_type` | enum | ✅ | `boolean`, `slider`, `select`, `multiselect`, `text`, `number`, `textarea`, `color`. |
| `label` | localeLabel | — | Field label (falls back to the raw key). |
| `description` | localeLabel | — | Helper text shown under the label / in a tooltip. |
| `env_var` | string | — | Pelican startup variable to also update when this value is written. **When set, `min`/`max` become hard caps** (they bound the synced variable). |
| `config` | object | — | Type-specific options (below). |

### `config` options per `display_type`

| display_type | Relevant `config` keys |
|--------------|------------------------|
| `boolean` | `true_value` (default `"true"`), `false_value` (default `"false"`), `default` |
| `slider` | `min`, `max`, `step`, `float`, `suffix`, `default` |
| `number` | `min`, `max`, `step`, `float`, `suffix`, `default` |
| `select` | `options` (required), `default` |
| `multiselect` | `options` (required), `separator` (default `,`), `default` |
| `text` | `max_length`, `regex`, `default` |
| `textarea` | `max_length`, `default` |
| `color` | `format` (e.g. `hex`), `default` |

- `options`: `[{ "value": "raw", "label": { "fr": "...", "en": "..." } }]` (`value` required).
- `min`/`max`: for non-env params they are **soft** (a player may type beyond
  them manually); for env-linked params they are **hard**.
- `float`: allow decimals. If omitted, decimals are inferred from a fractional
  `step` or a decimal `default`.
- `default`: used as the fallback when the key is missing from the real file.
  Never overwrites an existing file value.

---

## 4. Generating a template with an AI

Prompt the model with this whole document **plus** the example below, then:

```
Using the Easy Configuration template format and example above, generate a
template JSON for <GAME>. Its config file is <PATH> in <FORMAT> format. Here are
the real keys and their meaning: <paste keys / a sample config>. Requirements:
- Cover every documented parameter with FR + EN labels and a short description.
- Pick the most fitting display_type and constraints (min/max/step/options).
- Use sections for ini/toml. Set columns to 2. Enable boost and blacklist ports.
- Output ONLY valid JSON conforming to the schema (no comments).
```

Validate the result against `schema/easy-config-template.v1.json` before shipping
(every file in `samples/` is checked by `SampleTemplatesTest`).

---

## 5. Complete example

See **[`samples/example-template.json`](../samples/example-template.json)** — a
schema-valid template that exercises every display type, a flat `properties`
file and a sectioned `ini` file, `section_labels` + `section_whitelist`, an
`env_var` link, `columns`, and `boost`. Use it as the few-shot example.
