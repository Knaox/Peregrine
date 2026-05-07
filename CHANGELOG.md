# Changelog

All notable changes to the Peregrine panel are documented in this file.

The format is loosely based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
