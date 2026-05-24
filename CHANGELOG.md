# Changelog

All notable changes to the Peregrine panel are documented in this file.

The format is loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Workflow** — Add one bullet per change under **[Unreleased]** as you work, in the right group (`Added` / `Changed` / `Fixed` / `Removed` / `Security`). When you ship, rename **[Unreleased]** to the version you set in `config/panel.php` with today's date (`## [1.2.3] — YYYY-MM-DD`), start a fresh empty **[Unreleased]** on top, commit, and `git push origin main`. The Release workflow publishes that section verbatim as the GitHub release notes for tag `vX.Y.Z`.

## [Unreleased]

### Fixed

- **Easy Configuration → 1.2.3** (plugin-only release, not tied to a panel version). The game-config editor no longer blanks out entirely ("the game server configuration can't be reached") when a *single* config file fails to read: a file that is absent (404) or unreadable (Wings 5xx / timeout / a per-file throttle or bad path) is now skipped, and every config file that loaded is still shown; the full "unreachable" message appears only when *nothing at all* could be read. This bit multi-file templates (e.g. Hytale's server + world configs) where one file's path errored — and it was never a JSON-format problem (the parser tolerates malformed input by design). Also refreshes the bundled **official Hytale template** with a complete, fully bilingual (FR/EN) server + `default`-world schema.

## [1.0.0-alpha.13c] — 2026-05-24

### Fixed

- **Easy Configuration → 1.2.2.** Editing a template imported via "Import official" (slug ids such as `ark-survival-ascended`) returned `404` in production. The alpha.13b guard constrained `templates/{id}` to digits (`whereNumber`), which fixed the `import-official` 405 but wrongly rejected the **non-numeric slug ids** that official templates use (they're stored as `<id>.json`). The constraint now excludes only the reserved static segments (`import` / `import-official` / `example`) while accepting both slug and numeric ids — so the official import **and** template editing both work under cached routes. Verified under `php artisan route:cache` for `import-official` (POST), slug ids (GET/PUT/DELETE), `example`, and numeric ids.

## [1.0.0-alpha.13b] — 2026-05-24

### Fixed

- **Easy Configuration → 1.2.1.** Importing the official template catalog failed in production — `POST …/admin/templates/import-official` returned `405 (Supported methods: GET, HEAD, PUT, DELETE)`. Under `php artisan route:cache` (run in the prod Docker image) the unconstrained `templates/{id}` wildcard shadowed the static `import-official` / `import` / `example` POST routes; declaration order alone only protects the un-cached dev matcher, which is why it worked locally but not in prod. The `{id}` routes are now constrained to digits (`whereNumber('id')`) so the static routes resolve identically in dev and prod. Note: imported templates were never at risk — they live in `storage/app/easy-config/templates` (outside the plugin directory), so they already persist across plugin updates.

## [1.0.0-alpha.13] — 2026-05-24

### Added

- **Minecraft console quick-fixes.** The live console now detects two common boot failures and offers a one-click fix — surfaced on both the console and the server home page, gated by the relevant server permissions:
  - **EULA not accepted** → a prompt writes `eula=true` to `eula.txt` and cleanly power-cycles the server (force-stop → wait until offline → start).
  - **Incompatible Java version** → a picker lists the egg's Docker images (plus a `pelican-eggs/yolks` Java 8 / 11 / 17 / 21 fallback when the egg ships too few), highlights the recommended one — derived from the `UnsupportedClassVersionError` class-file version in the log — then applies it and cleanly power-cycles. The image switch is validated against the egg's allowed set and preserves the egg / startup command / environment (only the image changes).
- **Configurable "remember me" duration.** `/admin/settings → General` now exposes the remember-me cookie lifetime (in days, 1–3650). Stored in the database so it survives a Docker redeploy and applies to new logins without a `config:clear` — same DB-backed runtime pattern as the application timezone.
- **Console history + auto-clear on shutdown.** When the server is stopped the console clears to the "server is offline" placeholder; a new **History** button opens the last 1000 log lines, filterable by the number of lines to show.

### Plugins

- **Easy Configuration → 1.2.0.** Read-only browsable configuration editor while the server is running (instead of a hard stop-the-server lock for read access); clearer state that distinguishes an unreachable Wings daemon from genuinely absent files; an accessibility pass (accessible names + pressed state on the custom controls); a Vitest frontend test suite; and an **AI prompt generator** tab in the template editor — a pure prompt builder scoped to the parameters/sections detected on the server, to hand to an AI when authoring a new template.

### Fixed

- **`TwoFactorTest`** assertions aligned with the actual `auth-2fa:*` error-key format returned by the 2FA challenge endpoints (test-only; no runtime behaviour change).

## [1.0.0-alpha.12b] — 2026-05-24

### Changed

- **Easy Configuration is now a first-party _official_ plugin — bundled and installed out-of-the-box, like Invitations.** It ships in the panel source (`plugins/easy-configuration`, tracked in this repo with its templates), activates automatically on a fresh install, and cannot be uninstalled. Authored by Peregrine; managed from `/admin/plugins` and its template manager.

### Plugins

- **Easy Configuration → 1.0.0.** Full breakdown of the plugin now shipped bundled with the panel:
  - **Configuration editing.** A Nitrado-style **Game configuration** section on the server overview: one card per parameter (label, description, control on the right), grouped into collapsible sections, edited via sliders / toggles / dropdowns / colour pickers instead of raw text. Values are read live from the real file and written back with a **lossless round-trip** that rewrites only the values you changed (comments, key order and untouched lines survive byte-for-byte). Supported formats: `.properties`, INI, YAML, JSON, TOML, Palworld `PalWorldSettings.ini`, and The Forest. The editor locks behind a *stop-the-server* overlay while it runs; saves are atomic across files (`Cmd/Ctrl+S`); the layout is admin-configurable (1–3 columns).
  - **Auto-discovery + admin annotation.** Keys present in the real file but absent from the template are surfaced automatically (type inferred) and stay editable as raw fields; an admin can **annotate** any discovered parameter into the template in one click — FR/EN label, description, display type and constraints — which then applies to every server of that egg. Adding a repeatable key (e.g. ARK `ConfigOverrideItemMaxQuantity`) appends a new line instead of overwriting an existing one.
  - **Boosts — multiply _or_ divide.** Schedule a temporary, optionally recurring (daily / weekly / monthly) change to selected numeric values: **×** to multiply or **÷** to deboost (shrink a value or interval) per parameter, with an optional per-parameter ceiling. The server is automatically stopped and restarted around each boost (and each recurrence) to apply the change cleanly; cancelling an active boost is race-safe — it never resurrects a recurring boost or leaves the row stuck.
  - **Copy + startup-variable linking.** Copy a configuration to your other servers of the same egg (multi-step picker, background job, per-server recap). A parameter can be linked to a Pelican startup variable so editing it syncs both; for linked values `min`/`max` are hard caps, soft elsewhere so a player can override.
  - **Templates as shareable JSON.** Pure render schemas (they never store values) managed from the admin with visual + raw-JSON editors and a live preview. Ships with a JSON Schema, ready-to-fork samples, a one-click **example template** (openable from the admin), and a complete authoring guide (`docs/TEMPLATE_AUTHORING.md`) you can hand to an AI to generate new templates.
  - Permissions: `easyconfig.read` / `easyconfig.write` / `easyconfig.boost` (subusers via the Invitations plugin); template management requires `is_admin`.

## [1.0.0-alpha.12] — 2026-05-24

### Added

- **Copy a schedule to other servers.** From a server's Schedules page, a schedule — its cron timing **and** every task — can be copied onto one or more of your other servers in a single action. Targets are picked from the servers you can create schedules on (the source excluded), and each target's result is reported independently, so one failure never blocks the others. Tasks are recreated exactly as the manual "create task" flow does (a backup task carries no payload, the sequence is reset) rather than copied verbatim.
- **Invite a user to several servers in one email.** The Users & Invitations page can now invite the same person to multiple servers at once; they receive a **single** email whose link accepts every server in the batch in one go (accept-all) — provisioning the Pelican subuser and granting access on each. A new **"Copy access"** action on an active user replicates their permission set to your other servers. Multi-server targets are restricted to servers running the **same egg** (permissions are egg-specific). Backed by an additive `batch_id` column — single-server invites behave exactly as before.
- **Easy Configuration plugin (bundled, auto-activated, official).** A new first-party Peregrine plugin that adds a **Game configuration** section to the server overview — edit `server.properties`, `GameUserSettings.ini`, `config.yml`, … through a Nitrado-style UI (sliders, toggles, dropdowns, colour pickers) instead of raw text, with a lossless round-trip that rewrites only the values you changed across `.properties`, INI, YAML, JSON, TOML, Palworld and The Forest formats. Ships with the panel and activates out-of-the-box, like Invitations.
  - **Auto-discovery + annotation.** Keys present in the real file but absent from the template are surfaced automatically (type inferred) and stay editable; an admin can **annotate** any discovered parameter into the template — FR/EN label, description, display type, constraints — in one click, applied to every server of that egg.
  - **Boosts (multiply _or_ divide).** Schedule a temporary, optionally recurring change to selected numeric values: **×** to multiply or **÷** to deboost (shrink a value or interval) per parameter, with an optional per-parameter ceiling. Servers are automatically stopped and restarted around each boost, and cancelling is race-safe — a cancelled recurring boost is never resurrected.
  - **Copy & startup-variable linking.** Copy a configuration to other servers of the same egg, and link a parameter to a Pelican startup variable so editing it syncs both (min/max enforced as hard caps for linked values, soft elsewhere so a player can override).
  - **Templates as shareable JSON.** Pure render schemas managed from the admin (visual + raw-JSON editors with a live preview), plus a one-click **example template** and a complete authoring guide you can hand to an AI to generate new ones.
- **Editable server startup variables.** The server overview surfaces the egg's startup/environment variables for editing, with a registry that lets plugins (mods / modpack installers, …) claim the variables they manage so those are flagged as "linked" instead of offering a duplicate editing surface.

### Changed

- **Users & Invitations — cleaner, faster.** The invite/edit dialogs are now proper centered modals, the permission picker gains a global **"select all"** alongside the per-group toggles, and an invitee who is already signed in (matching email) is **auto-accepted in a single step** — the public invite page now hydrates the session itself, so a logged-in user is no longer bounced through the login screen before accepting.

### Fixed

- **Invitation email templates no longer leak between single- and multi-server sends.** `SettingsService::get(key, default)` caches the resolved value — including the fallback default — per key, so the first caller's default could be served to a later one. The mailer now resolves its default body/subject in PHP, so a batch email always renders its own template.

## [1.0.0-alpha.11b] — 2026-05-23

### Fixed

- **Scheduled backups now appear on their own.** A backup created outside the panel — a scheduled backup task, or one made directly on Pelican — bypasses the cache busting that the panel's own create/delete actions trigger, so it used to stay hidden behind the backups-list cache for up to ~2 minutes (and would never show without a manual refresh while sitting on the page). The server-side idle cache TTL drops from 120s to 30s, and the backups page now refetches on window focus and polls on a slow 30s idle cadence (paused while the tab is backgrounded), so a scheduled backup surfaces within ~30s — still well within Pelican's per-server throttle (~2 req/min from this endpoint).

## [1.0.0-alpha.11] — 2026-05-23

### Added

- **Schedules are now editable, not just deletable.** Each task in a schedule can be edited in place (action, payload, timing) instead of being removed and re-created, and an existing schedule's own settings (cron expression, name, active state) can be changed after creation. A preset task added to a new schedule shows up immediately without a manual page refresh. Backed by a new `UpdateScheduleRequest` gated on the `schedule.update` permission, so a user who can edit but not create schedules no longer hits a 403.
- **Per-server feature limits surfaced in the panel.** The server resource exposes each server's real `feature_limits` (backups / databases / allocations), and the Backups, Databases and Network pages now show the live quota and remaining headroom ("3 / 5 used"), with the create action disabled and a clear message when the limit is reached.
- **Richer file-manager feedback.** Uploads show a live percentage and survive cross-navigation (the directory is now URL-backed, so refreshing or sharing a link lands in the right folder), and long-running compress / decompress / pull operations display an indeterminate activity bar instead of looking frozen. The bulk-selection bar offers **Extract** when a single archive is selected.
- **Post-login redirect for invitation links.** Login and the 2FA challenge now honour a `?redirect=` target, so an invitation URL (`/login?redirect=/invite/{token}`) returns the invitee to the invite page after authenticating — where it now **auto-accepts** when the signed-in email matches — instead of dropping them on the dashboard. A new `safeRedirectPath` guard rejects open-redirect tricks (protocol-relative, backslash, absolute URLs) and falls back to the default landing route.

### Changed

- **Startup variables regrouped in the permission picker.** Startup/environment variables now sit under the Overview group, renamed **"Server Configuration"**, for a clearer permission layout.

### Fixed

- **`feature_limits` are read from Pelican, not the catalog config.** The panel now reflects the server's actual limits as Pelican enforces them, rather than the (possibly stale) values from the catalog `ServerConfiguration`.
- **Backups in progress are tracked correctly.** The backup list polls while a backup is still running and uses a short cache TTL during that window, so a freshly completed backup appears without a stale-cache delay.
- **Archive operations target the right path.** Compress now forwards the correct archive root to Pelican, fixing archives created at the wrong location.
- **Schedules: no more React #310 crash.** Stopped calling `useNamespace()` inside `actionIcon` (a conditional hook call), which could crash the schedules UI.
- **Plugin i18n loads at runtime.** Components re-render when a plugin's translation namespace is registered at runtime, so plugin strings no longer flash their raw keys on first paint.

### Security

- **Server sidebar entries are gated by `required_permission`.** A plugin's sidebar tab is only shown to users who hold the declared permission, and the Invitations read endpoints now enforce `user.read` — a subuser without it can no longer list invitations or subusers.

### Plugins

- **Server Invitations → 1.3.3.** Hardening of the accept / removal flows:
  - **Accept no longer gets stuck "pending".** The Pelican calls (account link + subuser provisioning) now run *outside* the DB transaction, and the local pivot + `accepted_at` are committed atomically only after they succeed — a late Pelican failure can no longer roll back local state while Pelican keeps the change. A new idempotent `syncSubuser` creates the subuser or updates an existing one (re-invite / leftover grant) instead of 422-ing on the duplicate.
  - **Removing a subuser truly revokes Peregrine access.** The local `server_user` pivot and any lingering invitation are dropped on removal — previously a removed user kept every permission through the pivot ("revoked but still in"). The target email is resolved before deletion on a best-effort basis (a throttled Pelican list never aborts the removal).
  - **Case-insensitive email matching throughout.** The host does not normalise emails (OAuth / shop accounts keep their original casing), so exact matches silently missed mixed-case accounts — granted permissions appeared not to apply, existing users were pushed into the register flow, and removals left the pivot intact. All lookups now match on `LOWER(email)`.
  - **Clean errors instead of 500s.** Pelican client calls throw `RequestException` (not `RuntimeException`); accept and register now catch `Throwable`, report it, and return a friendly retry message (`accept_failed`) so a transient Pelican hiccup no longer 500s and strands the invite.
  - **Already-registered invitees are sent to login**, and the accept-invite page is fully translated under the correct i18n namespace (strings served from the core common namespace).

### In progress

- **Easy Configuration plugin** — a new player-facing server-configuration editor (Nitrado-inspired UX, admin template management, copy-config-to-other-servers, scheduling) is under active development. It is **not** shipped in this release and is intentionally excluded from the marketplace bundle until it is complete.

## [1.0.0-alpha.10] — 2026-05-21

### Added

- **Two-phase cancellation lifecycle.** Disabling auto-renew (`cancel_at_period_end`) no longer suspends right away — the customer keeps the server until the paid period ends. Peregrine now records two dates: `scheduled_suspension_at` (period end) and `scheduled_deletion_at` (period end + `bridge_grace_period_days`). A new `SuspendScheduledServersJob` cron suspends the server once the suspension date passes (keeping the deletion date for the existing purge to hard-delete after grace), and re-enabling renewal clears both. Disabling renewal while a server is still provisioning (or after a failed provision) records the dates up front too, so nothing slips through between provisioning and period end. Adds the `scheduled_suspension_at` column (migration).
- **Editable scheduled dates in the admin.** The server edit form (Billing tab) gains a "Suspension planifiée le" picker next to "Suppression planifiée le", so both dates can be set or adjusted by hand to exercise the cancellation flow. New FR/EN labels + helper text.
- **Manual status change drives Pelican.** Setting a server's status to *suspended* in the admin now suspends it in Pelican, and switching a suspended server back to *active* unsuspends it. The Pelican call runs before the DB write, so a Pelican failure aborts the save with a notification instead of leaving the panel and Pelican out of sync.
- **Suspension sweep safety net.** The scheduled-suspension cron targets every non-terminal server (not just `active`), so a server powered off (stopped/offline) at its suspension date is still suspended, and servers whose deletion date elapsed during downtime without ever being suspended are caught — the purge's `status='suspended'` guard never strands them.

### Changed

- **Pelican mirror protection recentred on the Stripe subscription.** Pelican `created`/`deleted: Server` webhooks were neutralised as soon as a server carried *either* a `stripe_subscription_id` *or* a `server_configuration_id` — too broad, it stopped Pelican from creating or deleting servers that had no subscription. The guard now keys solely on `stripe_subscription_id`: subscription-backed servers are preserved on drift, while servers without a subscription are mirrored (manual Pelican creations, via a race-safe resolution) and removed locally when deleted in Pelican.
- **Server `status` carries only the lifecycle state** in the admin (active / suspended / provisioning / provisioning_failed). Power state (running / stopped / offline) is shown live in the frontend via the Wings websocket and is no longer persisted into `status`; the runtime sync only normalises any leftover power state back to `active`. The admin status select and table filter expose just the four lifecycle states.

### Fixed

- **Two-phase teardown scheduling could silently no-op** on a live server whose `status` had been overwritten with a power state by the old runtime sync (the guard checked `status === 'active'`, which a `running`/`stopped` row never matched). With power states out of `status`, the scheduling guard now matches the lifecycle status reliably.

### Admin UX

- **Every admin table column is now hideable** via the columns toggle (`Column::configureUsing(toggleable)`), and wide tables scroll horizontally + vertically through a global CSS render hook.
- **Missing translations added** (FR/EN): `fields.configuration`, `fields.has_configuration`, `fields.scheduled_suspension`.

### Internal

- **Automated GitHub releases.** A Release workflow cuts tag `vX.Y.Z` + a "Peregrine X.Y.Z" GitHub Release from the matching `CHANGELOG.md` section on every version bump pushed to `main`, idempotent on ordinary commits.
- **Versioned Docker images on push.** The Docker workflow tags the published image with the panel version (read from `config/panel.php`) on every push to `main`, so `ghcr.io/knaox/peregrine:<version>` ships alongside `latest` / `main`.

## [1.0.0-alpha.9] — 2026-05-20

### Added

- **Theme Studio — "Nice OAuth" sign-in mode** (`theme_login_oauth_first`, section Login). When enabled, the login card leads with the configured OAuth providers and tucks the local email/password form behind a "sign in locally" text link (progressive disclosure with an animated reveal); the "create an account" link stays visible. Intended for shops whose users authenticate through an OAuth IdP and have no local account, so they aren't nudged into a dead-end password form. Degrades automatically to the classic combined layout when no OAuth provider is enabled or local login is disabled — users are never stranded. Exposed end-to-end through the live Theme Studio preview (`theme.data.login.oauth_first`). Internally, `LoginFormCard` was split into `AuthField` + `LocalLoginForm`, and the studio preview payload extracted into `lib/themeStudio/buildPreviewPayload.ts`.
- **Stripe resubscribe: the `subscription_id` is now signed, plus an admin field for the shared secret.** Hardens the resubscribe URL contract so the shop side can verify the subscription identifier alongside the existing signature.

### Fixed

- **Stripe: a server scheduled for deletion is no longer auto-reactivated** when a stale or out-of-order `subscription.updated(active)` webhook arrives after the cancellation has already been processed.
- **Docker: pinned pnpm to 9.15.9** to bypass the strict-builds enforcement that broke the image build.
- **Docker: allow the esbuild build script** to run under pnpm strict mode.

## [1.0.0-alpha.8] — 2026-05-19

### Highlights

- **Loader-detection fallback** across the Minecraft plugin family. The new core `App\Services\Loader\FallbackLoaderResolver` cascades 3 sources — filesystem probe (`version_history.json`, `fabric-server-launcher.properties`, `libraries/.../{forge,neoforge,fmlloader}/<VER>/`, root-jar filename patterns) → `ModpackInstallation` row → egg name — whenever the primary MCJars fingerprint can't run (Wings unpatched) or doesn't identify the jar. Wired into both `minecraft-mods-installer` and `minecraft-plugins-installer` runtime detectors. Banner now appears for every detectable server, hidden only when no source has a confident answer (matches the contract "no banner if fingerprint not detected").
- **Modpack installer 1.1.0 — production-ready Forge/NeoForge install path.** A cascade of 6 inter-related bugs that left Forge 1.17+ servers unable to boot post-install is gone. New universal egg startup command handles all loaders in a single string. New self-install fallback recovers from CurseForge/Modrinth manifests that ship without a runnable launcher. Server is force-stopped before any install / uninstall so the install script never collides with a live JVM. No more symlinks anywhere on `/mnt/server` — `server.jar` is always a real file with a valid `Main-Class`, so `JavaVersionDetectionService` can fingerprint it reliably.
- **Server-compatibility surface in the modpack install modal.** New green "Serveur" badge on each version known to ship a server bundle ; new "Compatible avec un serveur uniquement" filter (default ON) that hides explicitly client-only versions while preserving unknown ones. Backed by a new `is_server_compatible: ?bool` field on the `ModpackVersion` DTO, populated by Modrinth, CurseForge, FTB and VoidsWrath (ATLauncher / Technic surface `null` — provider has no signal at the version level, the filter doesn't penalise them).
- **Version Changer 1.1.0 runs synchronously.** `ChangeVersionJob::dispatch()` → `dispatchSync()` — install completes inline in the HTTP request, the response carries the final `status` + `status_message` instead of a `log_id` to poll. Faster UX on the simple cases ; the same robust orchestration (kill → wait offline → wipe → MCJars install steps → image swap) still runs end-to-end.

### Added

- **`App\Services\Loader\FallbackLoaderResolver`** (new core service, ~290 LoC). Cascading detection: (1) filesystem probe via Wings `/files/list` for `version_history.json`, `fabric-server-launcher.properties`, `quilt-server-launcher.properties`, `libraries/.../<MC>-<F>/` directory matches, and root-level `paper-*.jar` / `forge-*.jar` / `neoforge-*.jar` patterns ; (2) optional read of the `modpack_installations` row when the Modpack Installer plugin is present ; (3) parsing the egg name (`Paper 1.20.1`, `Forge 1.19.2`, …). Returns the same `RuntimeArr` shape as the MCJars path, with a new `source` field for audit.
- **`PelicanClient::getEggStartup(int $eggId): ?string`** (Modpack Installer) — fetches the authoritative `startup` template from the egg, used to recover from per-server `container.startup_command` overrides that contain debug junk (`readlink server.jar` was the production case that motivated the fix).
- **Modpack installer: `is_server_compatible: ?bool` on `ModpackVersion`** + Modrinth / CurseForge / FTB / VoidsWrath populate it. Exposed at `GET /api/plugins/minecraft-modpack-installer/servers/{id}/modpacks/{provider}/{modpackId}/versions`.
- **Modpack installer: server-compat badge + filter** in the install modal (green pill with server icon, "Compatible avec un serveur uniquement" toggle, count of server-ready versions, hidden gracefully when the batch has no signal). New i18n keys under `install_modal.server_filter_*` / `install_modal.server_compat_badge*` in FR + EN.
- **Modpack installer: self-install fallback** in `finalize_jar` — when the pack extraction doesn't yield a bootable launcher, the install script inspects `libraries/`, `settings.cfg` (AllTheMods style), and the pack's startup scripts (`run.sh`, `start.sh`, `startserver.sh`, `ServerStart.sh`, `LaunchServer.sh`) to deduce `(loader, mc, loader_version)`, then re-runs the corresponding `install_*` routine. Bounded by a recursion guard.
- **Modpack installer: universal startup command** in the egg JSON: `java $([[ -f user_jvm_args.txt ]] && printf %s "@user_jvm_args.txt") -Xms128M -XX:MaxRAMPercentage=95.0 -Dterminal.jline=false -Dterminal.ansi=true $([[ ! -f unix_args.txt ]] && printf %s "-jar {{SERVER_JARFILE}}" || printf %s "@unix_args.txt") nogui`. Handles Vanilla / Paper / Purpur / Fabric / Quilt / legacy Forge ≤1.16 via `-jar`, and modern Forge 1.17+ / NeoForge via `@unix_args.txt`, in a single conditional shell expression.

### Changed

- **Modpack installer: post-install + uninstall + rollback restore the egg template's startup, not the per-server snapshot.** A `pelican_startup_snapshot` capturing a debug `readlink server.jar` (or any other one-off override) was being faithfully restored after every install, leaving the server unable to boot. Cascade is now `egg template > snapshot > hardcoded default`. Applied to `PollInstallStatusJob::finalizeInstall`, `PollInstallStatusJob::beginUninstallPhase2`, `UninstallModpackJob::handle`, `UninstallModpackJob::bestEffortRollback`, and `InstallationRollbackService::rollbackToSnapshot`.
- **Modpack installer: every install / uninstall force-stops the server first.** New `stopServerAndWaitOffline()` helper in both `InstallModpackJob` and `UninstallModpackJob` — sends Pelican `kill` (idempotent on already-offline) and polls `getServerResources()` until state reads `offline`, capped at 30 s. Eliminates EBUSY on `wipeServerFiles`, Wings 409 ServerStateConflictException on `/reinstall`, and live-JVM-vs-installer container races.
- **Modpack installer: `ServerRuntimeDetector` (mods + plugins variants)** now collapse intermediate statuses (`unknown`, `wings_not_patched`) into `null` after the fallback resolver also fails. The React `LoaderBanner` only ever renders the "ok" path (proper loader + version + logo) or nothing — matching the operator's explicit UX contract.
- **CurseForge + Modrinth `listCategories()` cache arrays, not DTOs.** The previous `Cache::remember(…, fn () => $arrayOfModpackCategoryDtos)` would `__PHP_Incomplete_Class` when the cache was deserialised in a request whose autoloader hadn't yet registered the plugin namespace. Cache key bumped to `:v2` so the pre-fix entries are invalidated. Rehydration to DTOs happens after the cache returns.
- **Public Peregrine `Server` startup is patched via the egg template on swap-back**, not the pre-install snapshot — see the modpack-installer paragraph above ; the same cascade ships in `InstallationRollbackService` so the rollback path benefits from the fix even when the install job never reached its happy path.

### Fixed

- **Modpack installer: `install_forge` no longer writes `run.sh` as `.peregrine-server-jar` marker** for Forge 1.17+ installs (where the installer produces only `run.sh` + `libraries/`, no fat jar at root). The marker was then resolved by `finalize_jar`, which symlinked `server.jar -> run.sh` ; the next boot crashed with `Error: Invalid or corrupt jarfile server.jar`. Mirror of the gate `install_neoforge` already had.
- **Modpack installer: `finalize_jar` no longer relies on symlinks anywhere.** All `ln -sf` are now `mv -f` / `cp -f` (real files), so `JavaVersionDetectionService::detect()` can `hash_file()` `server.jar` without hitting broken-link / `lstat`-vs-`stat` divergence / Wings file-API quirks. Modern Forge / NeoForge installs additionally copy `libraries/.../fmlloader/<VER>/fmlloader-<VER>-server.jar` (or `libraries/cpw/mods/bootstraplauncher/<VER>/bootstraplauncher-<VER>.jar` as fallback) to `server.jar` for fingerprinting — the file is never executed at runtime (egg startup picks `@unix_args.txt`), but its `Main-Class` is what makes the Java detection succeed.
- **Modpack installer: `EggImporter::ensureImported()` no longer lies about the cached fingerprint** when the egg already exists in Pelican. The pre-fix path cached the new fingerprint while leaving Pelican on the old script forever ; every subsequent install ran the stale install bytes with no operator-visible signal. Fingerprint mismatch now always triggers a delete + re-import, guaranteeing Pelican holds the on-disk script.
- **Modpack installer: `user_jvm_args.txt` sanitisation post-install.** New `sanitize_user_jvm_args()` strips any `-Xms` / `-Xmx` lines shipped by the modpack, so the egg startup's `-XX:MaxRAMPercentage=95.0` keeps memory inside the operator-assigned container limit. Without it, packs hardcoding `-Xmx12G` would OOM smaller Pelican containers regardless of the assigned RAM.
- **Modpack installer: `libraries/` probe in `finalize_jar`** now looks in `libraries/.../fmlloader/<VER>/` (the actual location of the Forge moderne / NeoForge bootstrap jar) instead of `forge/<VER>/` (typically metadata-only). Three additional fallback layers : `cpw/mods/bootstraplauncher/` (always has a `Main-Class`), `unix_args.txt` reference parsing, and a full `libraries/` sweep. Hard-fails with a diagnostic dump (every jar found under `libraries/net/*` and `libraries/cpw/*`) instead of looping into self-install when no candidate exists.

### Plugin releases

- **Minecraft: Modpack — Installer 1.1.0** — `is_server_compatible` field + modal badge & filter, universal egg startup, self-install fallback, full symlink elimination, sanitised `user_jvm_args.txt`, content-aware `EggImporter` re-push, kill-before-install/uninstall, egg-template-first startup restoration, every `finalize_jar` symptom from the alpha.7 production reports.
- **Minecraft: Mods — Installer 1.1.0** — injects the new `FallbackLoaderResolver`, banner now appears via filesystem / modpack / egg-name signals when MCJars can't run.
- **Minecraft: Plugins — Installer 1.1.0** — same as above ; CurseForge `listCategories` no longer ships `__PHP_Incomplete_Class` errors after a cache deserialise miss.
- **Minecraft: Version — Changer 1.1.0** — `ChangeVersionJob` runs synchronously via `dispatchSync()`, response carries the final status instead of a `log_id` to poll.
- **Hytale: Mods — Installer 1.0.0** — re-packaged unchanged, included in this release so the marketplace can serve every official Minecraft / Hytale plugin from a single tag.

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

### Post-release hotfixes

These 9 commits landed on `main` between the original `chore(release): 1.0.0-alpha.7` commit and the published `v1.0.0-alpha.7` GitHub tag. They are part of this release.

#### Added

- **Duplicate `ServerConfiguration`** — row action and bulk action on `/admin/server-configurations`. Auto-suffixes the `internal_name` (`-copy`, `-copy-2`, `-copy-3`, …, with a 999-iteration safety net) and strips an existing `-copy[-N]` from the source so a clone of a clone produces `mc-2gb-copy` instead of `mc-2gb-copy-copy`. Pivot links to shops are intentionally NOT carried over — the clone is invisible to every shop until explicitly authorized, mirroring the "create from scratch" flow. (`61f5dd3`, `d258fbc`)
- **`ResourceTemplate` model** — a reusable named bundle of Pelican specs (RAM, CPU, disk, swap, I/O weight, cpu_pinning) that any number of `ServerConfiguration`s can share. Editing the template propagates to every bound configuration in one shot, including a fan-out of `configuration.updated` outbound webhooks (one per bound config). New Filament resource at `/admin/resource-templates` with row + bulk Duplicate actions, and a corresponding `<Select>` + read-only specs preview on the Server Configuration form. Includes a back-fill migration that materialises one template per distinct spec tuple on existing rows before dropping the inline columns. (`e427955`)
- **Auto-pivot shops ↔ configurations** — creating a `ServerConfiguration` now attaches it to every existing `Shop` with `is_visible=true`, and creating a `Shop` attaches every existing `ServerConfiguration` to it. The multi-shop scoping rules in the API + `StripeCheckoutHandler` stay enforced, but the default is "everything visible everywhere" — which matches how single-shop deployments actually use the catalog. Idempotent via the pivot's UNIQUE constraint. (`078ad1f`)
- **Filament `Shops` cluster** — `ShopResource`, `ServerConfigurationResource` and `ResourceTemplateResource` are now bundled under a single sidebar entry that opens onto a sub-navigation listing the three resources, mirroring the pattern the Settings menu already uses. Each resource keeps its own routes and CRUD ; only the navigation rendering changed. (`078ad1f`)

#### Changed

- **Resubscribe URL contract is now keyed on `configuration_id`**, not `internal_name`. The HMAC payload becomes `{server_id}|{configuration_id}|{ts}` and the shop joins back to its local `Plan` via the FK on its `peregrine_configurations` mirror, never via slug↔internal_name. Removes the implicit "shop-side `Plan::slug` must match Peregrine-side `ServerConfiguration::internal_name`" convention that broke the resubscribe flow whenever a Plan was renamed shop-side. The `{configuration}` placeholder is still interpolated for legacy templates but is no longer covered by the signature ; admins migrating to v1 should switch to `{configuration_id}`. Coordinated with a SaaSykit-side fix shipped separately. (`6c711dc`)
- **`/admin/stripe-settings` third-party billing card** clarifies that pointing a third-party billing system (WHMCS, Paymenter, …) at `/api/pelican/webhook` requires the Pelican webhook secret to be configured first, and links directly to `/admin/pelican-webhook-settings`. The previous wording made it sound like the endpoint just worked out of the box. (`3bd37ee`)
- **Customer email cadence** collapses from 3 Peregrine emails per checkout to 2. The intermediate "we created your server" notification (`SendServerReadyNotification`, fired on `ServerProvisioned` = Pelican row created) is silenced — the listener stays registered as a no-op so any auto-discovered binding doesn't error, but the customer no longer receives an extra email between "Paiement confirmé" and "🚀 Votre serveur est jouable". The "playable" email now optionally inlines a "Set my password" CTA + 7-day reset link, but only when the user has neither a local password nor a linked OAuth identity (= account just created during checkout, never signed in yet). (`699d0bd`)

#### Fixed

- **OAuth multi-consent loop on Shop sign-in** — `SocialAuthService::redirectUrl()` was forcing `prompt=consent` on every Socialite redirect, which made Passport (on the Shop side) skip its `hasGrantedScopes()` check and re-display the consent screen on every login. Drops the parameter ; standard OAuth2 flow now applies (consent on first login, silent reuse afterwards). The original "silent re-link papercut" rationale for forcing consent is documented in the new comment block ; if it ever bites again it'll be fixed surgically by revoking the token at unlink time. (`699d0bd`)
- **Documentation of the Stripe metadata `peregrine_configuration_id` key** — every place the integration docs mentioned the key now spells out that the value must be the **upstream** `ServerConfiguration.id` returned by `GET /api/v1/configurations`, not a shop-side mirror's primary key. The ambiguity had bitten one live shop (SaaSykit) with `skipped: unknown_configuration` rejections — fixed shop-side in the matching SaaSykit commit, locked into the docs here so the next integrator (in-house WordPress plugin, custom storefront, …) can't repeat the mistake. (`51ba657`)

#### Removed

- **`BridgeSyncLog` Filament resource** + the underlying `bridge_sync_logs` table. Both were already orphans on the alpha.7 branch (the legacy bridge they audited was deleted in the main alpha.7 commits) — this drop just cleans up the leftover wiring so admins don't see a dead resource in the navigation. (`15c6aa2`)

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
