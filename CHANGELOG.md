# Changelog

All notable changes to the Peregrine panel are documented in this file.

The format is loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-alpha.7] — 2026-05-09

### Highlights

- **Public integration platform** (5-commit rollout). Replaces the legacy Bridge subsystem (push-based, single-shop, custom-HMAC) with a typed pull-based API + outbound Standard Webhooks suitable for any third-party billing surface (Paymenter, WHMCS, custom storefronts). Peregrine no longer cares "which billing mode am I in" — it just exposes capabilities and reacts to Stripe events.
- **Public API v1** under `/api/v1/*`: `GET /shop/me`, `GET /health`, `GET /configurations`, `POST /orders` (with `Idempotency-Key`), CRUD `/webhook-endpoints`. Authenticated per-shop via the new `EnsureShopApiKey` middleware (hashed-key bearer tokens, rotation-friendly).
- **Standard Webhooks outbound** (standardwebhooks.com spec) — Peregrine now emits signed events (`server.created`, `server.suspended`, `payment.succeeded`, …) to shop-managed endpoints, with retries via `spatie/laravel-webhook-server` and per-attempt audit rows visible at `/admin/webhook-deliveries`.
- **Peregrine PHP SDK** (`packages/peregrine-shop-sdk/`) — Guzzle-backed client + Stripe metadata builder + standard-webhooks verifier; ships in the monorepo, will move to its own packagist package in a later release.

### Added

- **`Shop` + `ShopApiKey` + `shop_server_configuration` pivot** — a shop is now a first-class entity with its own hashed API keys, last-used timestamps, expiry/revocation columns and an explicit allow-list of `ServerConfiguration`s it may provision against. Filament resource at `/admin/shops` with key-rotation actions.
- **`servers.external_order_id`** column — the shop's own order/invoice reference, propagated through `ProvisionServerJob` so customer support can pivot from "shop order #X" to a Peregrine server in one click.
- **Outbound webhook plumbing** (5 migrations: `webhook_endpoints`, `webhook_events`, `webhook_deliveries`, `webhook_delivery_attempts`, `api_idempotency_keys`). `EmitWebhookEventAction` fans out one delivery per subscribed endpoint; `DispatchWebhookDeliveryJob` defers to spatie's webhook-server for retries with exponential backoff.
- **`StandardWebhookSigner`** — base64 HMAC-SHA256 over `msg_id.timestamp.payload`, headers `webhook-id` / `webhook-timestamp` / `webhook-signature`. Verifiable by any Standard Webhooks consumer (a symmetric `StandardWebhookVerifier` ships in the SDK).
- **Stripe pipeline refactor** under `app/Bridge/Stripe/` — fat handlers split into discrete actions (`ResolveStripeMetadataAction`, `HandleRefundAction`, `HandleDisputeAction`) wired through an `EventActionMapper`; metadata errors raise `BridgeMetadataException` with stable error codes.
- **`/admin/stripe-settings`** Filament page + `/docs/stripe-settings` + `/docs/stripe-metadata` operator references — replaces the deleted `BridgeSettings` page.
- **`/admin/webhook-endpoints` + `/admin/webhook-deliveries`** Filament resources, plus `WebhookHealthWidget` (24 h success rate + queued depth on the dashboard).
- **`IntegrationStatusService`** (`hasStripeConfigured()` / `hasActiveShop()`) — single source of truth for the integration badges, replacing `BridgeModeService`.
- **`/docs/shops` + `/docs/integration-guide`** — operator-facing how-to for both flavours of integration.
- **i18n bundles** (FR/EN) for every new surface: `server_configurations`, `shops`, `shop_api_keys`, `stripe_settings`, `webhook_endpoints`, `webhook_deliveries`, `api_v1`.

### Changed

- **`ServerPlan` renamed to `ServerConfiguration`**, columns renamed in lockstep (`servers.plan_id` → `servers.server_configuration_id`, `bridge_sync_logs.server_plan_id` → `bridge_sync_logs.server_configuration_id`). The legacy table mixed technical provisioning config (CPU, RAM, port mapping, env vars) with commercial pricing — the commercial columns are dropped here and now live on the third-party shop side. New `internal_name` + `name_template` columns let admins keep a stable technical key while still rendering a human-friendly display name.
- **`PelicanMirrorReconciler`** no longer guards on bridge mode — it runs whenever the Pelican webhook receiver is enabled and is a no-op when there's nothing to mirror.
- **`PelicanWebhookDispatcher`** suppresses Pelican-driven `User.created` events only when Stripe is wired (Peregrine drives users itself in that flow).
- **Customer notifications** (`PaymentConfirmed`, `ServerInstalled`, `ServerReactivated`, `ServerReady`, `ServerSuspended`, `TrialWillEnd`) are now also emitted as outbound webhook events for shops to react to.
- **`spatie/laravel-webhook-server` ^3.10** added to `composer.json`.

### Removed

- **Legacy Bridge subsystem**: `BridgeMode` enum, `BridgeModeService`, `BridgeSettings` Filament page, `VerifyBridgeSignature` middleware, `PlanSyncController` + the `/api/bridge/{ping,plans/upsert,plans/{id}}` route group, `BridgeUpsertPlanRequest`, the `app/Plugins/Bridge/*` skeleton, the `bridge_settings` rows in the `settings` table (dropped via migration `000019`), and the matching FR/EN i18n bundles + `BridgeSettingsTest` / `BridgePlanSyncTest`. Configuration sync is now `GET /api/v1/configurations` (the shop reads — it doesn't push).

### Security

- **Hashed shop API keys** (never stored in cleartext, displayed once at creation), explicit revocation and expiry columns, per-key `last_used_at` for anomaly detection.
- **Idempotency-Key support** on `POST /api/v1/orders` (the `api_idempotency_keys` table) prevents accidental double-provisioning if a shop retries a request.

---

## [1.0.0-alpha.6] — 2026-05-09

### Highlights

- **Plugin import via .zip**: admins can drop a plugin archive directly onto `/admin/plugins` and have it validated through 8 layers of defence (MIME + magic bytes, anti zip-slip, anti zip-bomb, symlink rejection incl. **CVE-2025-3445**, manifest validation, sandbox extraction with canonical-path check). Inline FR/EN documentation explains every check and the active limits.
- **`/admin/plugins` UI overhaul**: redesigned page with clickable stat cards header, underline tabs, live search + category filters on the marketplace, separate **Officials** and **Community** sections, Blade components (`<x-pg.btn>`, `<x-pg.pill>`), polished settings modal.
- **External marketplace plugins**: registry entries can now declare `external_url` (e.g. BuiltByBit). The install button becomes **"View on BuiltByBit"** and opens in a new tab — no download flows through Peregrine for premium plugins.
- **i18n inline bundle** (3b9e3e2): namespaces pre-compiled into a single `window.__I18N_BUNDLE__` global inlined in `app.blade.php`, eliminating the 100–300 ms FOUC of raw keys on slow connections. EN bundle = 11.4 KB gzipped, cold service call 1.48 ms / cached 0.24 ms.

### Added

- **`PluginUploadService`** (`app/Services/PluginUploadService.php` + `Concerns/ValidatesPluginUpload.php` trait) implementing the 8 defence layers:
  - MIME + extension whitelist + magic bytes check (`PK\x03\x04` / `PK\x05\x06` / `PK\x07\x08`)
  - `ZipArchive::CHECKCONS` for archive integrity
  - Anti zip-bomb: max entries (500), max extracted size (100 MB), max compression ratio (100:1)
  - Anti zip-slip: rejects absolute paths, `..` traversal segments, Windows backslashes
  - Anti symlinks (CVE-2025-3445): pre-extraction Unix-mode bits check + post-extraction `realpath` walk + `is_link()`
  - Per-file extension whitelist (configurable)
  - Manifest validation: kebab-case `id`, SemVer `version`, required `name`/`author`
  - Sandbox extraction → canonical-path check → atomic move (anti TOCTOU)
- **Inline plugin upload help** (`partials/plugins/help-doc.blade.php`) — collapsible doc card explaining the expected manifest format, the security checks performed and the active limits. Auto-translates to FR/EN via `__()`.
- **Livewire traits** :
  - `HandlesPluginUpload` (`Concerns/HandlesPluginUpload.php`) — wires `WithFileUploads` + the `updatedUploadedZip()` lifecycle hook
  - `HandlesPluginActions` — extracted from the page controller (install / update / activate / deactivate / uninstall + new `updateAllPlugins()` batch action)
- **Page `/admin/plugins` redesign**:
  - 4 clickable stat cards header (Installed / Active / Updates / Marketplace)
  - Global "Updates available" banner with "Update all" batch button
  - Underline tabs with counters
  - Live search + filter chips on marketplace (All / Officials / Community + clickable tag categories)
  - Two distinct sections (Officials with primary-tinted gradient + Community)
  - Polished settings modal (plugin logo + name in header)
- **Reusable Blade components** under `resources/views/components/pg/` :
  - `<x-pg.btn variant="primary|success|danger|warning|default|ghost" :loading-target="...">` — built-in Livewire loading state and `<a>`/`<button>` polymorphism
  - `<x-pg.pill variant="active|inactive|installed|external" :dot>`
- **Marketplace `external_url` field** — when set, the install button becomes a `target="_blank" rel="noopener noreferrer"` link to the vendor site (BuiltByBit detection adds an explicit "BuiltByBit" badge with chain icon).
- **Config section** `panel.plugin_upload` (in `config/panel.php`) with env vars: `PLUGIN_UPLOAD_ENABLED`, `PLUGIN_UPLOAD_MAX_SIZE`, `PLUGIN_UPLOAD_MAX_ENTRIES`, `PLUGIN_UPLOAD_MAX_EXTRACTED`, `PLUGIN_UPLOAD_MAX_RATIO`, `PLUGIN_UPLOAD_ALLOW_OVERWRITE`.
- **i18n**: ~30 new keys in `lang/{en,fr}/admin/plugins.php` covering stats, filters, sections, updates banner, upload zone, doc, errors, external badge.
- **Performance — i18n inline bundle (3b9e3e2)**: a service provider pre-compiles every namespace into one bundle, cached 6 h with mtime-keyed invalidation, served as a single inline global. The other locale stays lazy and is fetched once on language switch (instant on subsequent flips). Plugin i18n contract (`/api/plugins/{id}/i18n/{lng}`) is unchanged.

### Changed

- **`Plugins.php` Filament page** refactored from 385 → 216 lines. Actions extracted to `HandlesPluginActions`, upload to `HandlesPluginUpload`, all kept under the project's per-file ceiling.
- **Allowed extensions whitelist** for the `.zip` importer extended with conventional extensionless basenames: `LICENSE`, `README`, `CHANGELOG`, `AUTHORS`, `CONTRIBUTORS`, `COPYING`, `NOTICE`, `CODEOWNERS`. Plugins shipping a bare `LICENSE` no longer fail import.
- **Plugin importer ignores `__MACOSX/` and `._*` AppleDouble entries silently** — macOS resource-fork metadata that `zip` CLI stuffs into archives even with `-x "__MACOSX/*"`. Skipping them rather than aborting prevents user frustration without weakening security.
- **Plugin upload zone** receives drag-over visual feedback and an `is-uploading` opacity state during validation/extraction.
- **Mail layout** (`resources/views/mail/layouts/base.blade.php`) and the templated email wrapper now both source the brand name from `SettingsService::get('app_name')` first, falling back to `config('app.name')` then to a `'Peregrine'` default.
- **Repo housekeeping (c22dd3b)**: `MODPACK_FEATURE_SPEC.md` excluded from the versioned tree (kept locally as a working note).

### Fixed

- **Email invitations showed "LARAVEL" instead of the panel name** (plugin `invitations` 1.3.1 → 1.3.2): `ServerInvitationMail` was calling `config('app.name', 'Peregrine')` which reads `APP_NAME` from `.env` — but on Docker installs the env var is rarely resynchronised after the setup wizard. The canonical panel name lives in `SettingsService::get('app_name')` (the value set in `/admin/settings`). `ServerInvitationMail::build()` and `buildVariables()` now resolve the brand from the settings service first.
- **Plugin `.zip` upload required two attempts** to import: `wire:change="importPluginZip"` fired the server method before `wire:model="uploadedZip"` had finished streaming the file to `livewire-tmp/`. The first call saw `$uploadedZip = null` and bailed out silently; the second call found the previously-streamed file and succeeded. Replaced with the Livewire `updatedUploadedZip()` lifecycle hook which only fires after the upload completes.
- **`PluginUploadService` was importing `Symfony\Component\Filesystem\Filesystem`** — a class that's NOT part of Laravel's default dependency tree. Switched to `Illuminate\Filesystem\Filesystem` (loaded everywhere via the framework). API rewrites: `mkdir → makeDirectory(recursive: true)`, `remove → deleteDirectory`, `rename → move`. The original `Class … not found` import errors stopped at the very first guard.
- **`@once` directives** added around the `<style>` blocks in `partials/plugins/upload-zone.blade.php` and `help-doc.blade.php` so the same CSS isn't re-emitted if the partial is included multiple times in a single response.

### Security

- **Plugin `.zip` import hardened against CVE-2025-3445**: a recent class of zip-slip bypasses where attackers craft archives with symbolic-link entries pointing outside the extraction root. Two redundant guards: (1) the Unix mode bits in each ZIP entry's external attributes are inspected before extraction (any entry with the symlink bit `0xA000` aborts the import), and (2) every extracted file is walked post-extraction with `realpath` + `is_link` so an entry that managed to slip through is still rejected before the atomic move.
- **Anti zip-bomb defaults**: max 500 entries, max 100 MB total uncompressed size, max 100:1 compression ratio. A 1 MB malicious ZIP that decompresses to 1 GB is rejected with `admin/plugins.upload.errors.zip_bomb`.
- **Audit log on every import attempt** — the SHA-256 of the uploaded ZIP, the admin's user ID, the source IP and the request timestamp are written to the configured logging channel (`stderr` on Docker, surfaced in `docker logs`).

### Docker

- `Dockerfile`: `mkdir` + `chown www-data:www-data` extended to `/var/www/html/plugins` and `/var/www/html/storage/app/plugin-uploads`. The previous chown only covered `storage/`, `bootstrap/cache/` and `public/plugins/` — admin-uploaded archives land in the (untouched) `plugins/` directory, which would have failed with "Permission denied" on every import.
- `docker/entrypoint.sh`: re-asserts ownership on `plugins/` and `storage/app/plugin-uploads/` on every container boot. Necessary because the named volume `peregrine_plugins` can preserve previous-image ownership when reused after a rebuild.

---

## [1.0.0-alpha.5] — 2026-05-08

### Highlights

- **Full i18n refactor**: the two monolithic translation files (`lang/en/admin.php` at 1138 lines, `resources/js/i18n/en.json` at 985 lines) are now split into per-page namespaces — 23 PHP files under `lang/{en,fr}/{admin,auth}/*.php` + 19 JSON namespaces under `resources/js/i18n/locales/{en,fr}/*.json`. The largest single file is now `_shell.php` at 266 lines (–77%), `theme-studio.json` at 331 lines (–66%).
- **Lazy-loaded translation chunks**: each frontend namespace is a separate Vite chunk discovered via `import.meta.glob`, fetched on demand when a page mounts (only `common` and `auth-login` are eager). The plugin i18n contract (`useTranslation(pluginId)`) is preserved.

### Changed

- **Backend translation keys** moved to slash-namespaced paths (`__('admin/servers.list.title')`, `__('auth/social.providers.shop')`). Laravel's file-loader supports the slash natively — no custom resolver. The previous flat keys (`admin.X`, `auth.login.X`, etc.) are removed.
- **Frontend translation keys** moved to `ns:key` form (`t('server-overview:list.title')`, `t('auth-2fa:invalid_code')`). API error codes returned by the panel (`auth-social:provider_disabled`, `auth-2fa:required_admin_setup`, etc.) flow straight into i18next on the SPA side without any client-side translation lookup table.
- **Sidebar config defaults** (`ThemeDefaults::SIDEBAR_CONFIG`, `CardConfigResolver`) now ship the new key shape `server-shell:detail.{overview,console,…}`. A `LEGACY_LABEL_KEY_MAP` in `useSidebarConfig.ts` transparently migrates persisted `theme_settings.sidebar_config` rows so customised sidebars keep rendering translated labels — no DB migration required.

### Internal

- Migration scripts checked in under `scripts/i18n/`: `build-mapping.php`, `build-new-locale-files.php`, `rewrite-php.php`, `rewrite-ts.cjs`, `fill-missing-keys.cjs`, `find-missing-keys.cjs`, `verify.sh`. These produce a deterministic key mapping (`mapping.json`, 1031 backend + 800 frontend keys) and apply it byte-for-byte across 58 PHP files + 106 TS/TSX files. Keep them around to ease future namespace splits.
- New helpers: `resources/js/i18n/useNamespace.ts` (per-page lazy load + locale-switch refetch), `resources/js/i18n/pluginLoader.ts` (extracted from `config.ts` to keep the plugin i18n surface stable across config rewrites).

---

## [1.0.0-alpha.4] — 2026-05-07

### Highlights

- The **Minecraft Modpack Installer** is now an officially Peregrine-branded plugin with vastly richer search filters across all six providers (Modrinth, CurseForge, ATLauncher, Feed The Beast, Technic, VoidsWrath).
- The **server reinstall flow** gains a `wipe-and-reinstall` option and a new `ServerReinstallStarting` plugin event so plugins can clean state before the install script re-runs.
- The **Pelican client** is now resilient to in-flight install/reinstall states: HTTP 409 `ServerStateConflictException` no longer pollutes the panel's error log on every poll cycle.

### Added

- **Server actions**: `wipe-and-reinstall` option exposed on the Reinstall flow + live sidebar gate during modpack operations to prevent users from triggering conflicting actions mid-install.
- **Plugin event hook**: `App\Events\ServerReinstallStarting` is dispatched from `ServerController::reinstall` before the egg's install script runs again, letting plugins clean state tied to the previous server config (the modpack-installer plugin uses this to drop its installations row so the modpack tab no longer shows the pack as installed).
- **Modpack-installer — search filter expansion** (capabilities-driven, declared per provider):
  - **Sort modes**: relevance / popular / downloads / updated / newest / name / follows / plays / featured — each provider declares which it supports and the unified UI renders only those.
  - **Category / tag filter**: Modrinth (project categories), CurseForge (`/categories?gameId=432&classId=4471`), Feed The Beast (popular tags).
  - **Minecraft version filter** added for Feed The Beast (uses the `/byVersion/{mc}` endpoint).
- **Modpack-installer — install modal redesign**:
  - Scrollable list shows **every** version published for the pack (no longer truncated to the first 50).
  - Each row prominently displays the Minecraft version + loaders + release type, with release/beta/alpha colour pills.
  - **Local Minecraft-version selector** inside the modal narrows the list without forcing the user to close + reopen the dialog.
  - Auto-selects the most recent compatible release on open / on filter change.
  - Compatible vs incompatible options are visually distinguished — incompatible ones still selectable, just flagged.
- **Modpack-installer — admin tooling**:
  - `php artisan modpacks:import-egg --diagnose` prints a diff between Pelican's live egg variables and the bundled template — fast triage when a `PATCH /startup` returns "The X variable field is required".
  - `php artisan modpacks:import-egg --hard` deletes the Pelican egg and re-creates it from scratch (recovery path for variable-row drift after a partial import).

### Fixed

- **Pelican client (core)** — `PelicanClientService::getStartupVariables` now swallows HTTP 409 `ServerStateConflictException` and returns an empty array. Pelican replies 409 on read endpoints while a server is mid-install / mid-reinstall — that's documented behaviour, not an error worth bubbling. Putting the tolerance in the client service rather than in each consumer keeps it plugin-agnostic: any controller, queue job, console command, or third-party plugin benefits without having to know about Pelican's internal state model.
- **Modpack-installer — CurseForge MC-version filter**: filtering by an MC version (e.g. 1.8.9) was returning packs targeting other versions. Root cause was twofold:
  - `modLoaderType` is silently ignored when not paired with `gameVersion` (documented CF quirk) — the loader filter is now only sent alongside an MC version.
  - CF's `gameVersion` parameter is **file-scoped, not pack-scoped** — packs with an unrelated 1.20-tagged metadata file would surface when filtering 1.8.9. The plugin now post-filters search hits against `latestFilesIndexes` so only packs that actually publish files for the requested version are kept.
- **Modpack-installer — version list truncated**: `CurseForgeProvider::listVersions()` now paginates through every page (CF caps at 50/page); previously a popular pack with hundreds of files only showed the first 50 in the install modal.
- **Modpack-installer — egg import 500 on pre-existing UUID**: Pelican's `POST /api/application/eggs/import` does NOT upsert by UUID despite older comments claiming so — re-POSTing the same UUID raises HTTP 500 `UniqueConstraintViolationException` because the importer service does a blind `Egg::create()`. The plugin now looks up an existing egg via the new `PelicanClient::findEggIdByUuid()` and skips the POST when found.
- **Modpack-installer — install script hardening** (six fixes that landed across the cycle):
  - Stage downloads under `/mnt/server` instead of the install container's `/tmp` (large packs like Cobblemon overflowed the tmpfs and aborted the unzip).
  - Add progress logs + per-step download budgets so installs no longer appear stuck.
  - Declare `BB_MODPACK_OPERATION` on the egg, scrub orphan env vars on swap-back, flip local provisioning status during the install.
  - Fail loudly on broken installs instead of completing silently.
  - React #310 in the install modal (conditional `useTranslation`), MCJars camelCase, FTB error envelope, ATLauncher CDN.
  - Egg sync, real metadata persisted in the installations row, wipe-on-uninstall, Filament Resource for the admin settings.

### Internal

- Modpack-installer frontend bundle rebuilt (28.58 kB / 7.56 kB gzip).
- Modpack-installer ships with content-fingerprinted egg-import cache so script/template changes self-detect.
- Branding pass on the modpack-installer plugin (no more vendor leakage in author fields, copyright headers, User-Agent fallback).

---

For releases prior to alpha.4, please see the git log up to tag `v1.0.0-alpha.3`.
