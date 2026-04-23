# Authentication

Peregrine ships with a flexible, multi-provider authentication stack that
admins configure entirely from the Filament admin panel — no `.env` editing,
no redeploy when rotating keys.

- [Overview](#overview)
- [Local authentication](#local-authentication)
- [OAuth providers](#oauth-providers)
- [Two-factor authentication](#two-factor-authentication)
- [Forcing 2FA for admins](#forcing-2fa-for-admins)
- [Linked accounts](#linked-accounts)
- [Custom email templates](#custom-email-templates)

## Overview

Three independent capabilities that can be mixed freely:

1. **Local email/password** — classic Laravel auth against `users.password`.
2. **OAuth providers** — Google, Discord, LinkedIn, or any generic OAuth2
   server (useful to delegate login to an existing identity provider).
3. **2FA (TOTP)** — time-based one-time passwords from any authenticator app
   (Google Authenticator, Authy, 1Password, …) + bcrypt-hashed recovery
   codes.

Everything is toggled from **Admin panel → Settings → Auth & Security**.

## Local authentication

Enabled by default. Two independent toggles:

| Setting | Effect when off |
|---|---|
| **Local login enabled** | The email/password form disappears from `/login`. Users must sign in via an OAuth provider. |
| **Local registration enabled** | The `/register` page and its link disappear. New users can only arrive via OAuth. |

A typical setup for self-hosted installs keeps both on. A setup where you
delegate all identity to an external provider turns both off — then only
OAuth button(s) appear on the login page.

Password change happens from the user's profile page. It's automatically
disabled for users who signed up via OAuth and never set a local password.

## OAuth providers

Five providers are supported out of the box:

| Provider | Driver | Notes |
|---|---|---|
| **Google** | `laravel/socialite` core | Requires a Google Cloud OAuth 2.0 Client with the redirect URI Peregrine shows you |
| **Discord** | `socialiteproviders/discord` | Requires a Discord Developer Portal application |
| **LinkedIn** | `linkedin-openid` (modern OIDC flow) | Requires a LinkedIn OAuth 2.0 app with "Sign In with LinkedIn using OpenID Connect" |
| **Custom / Shop** | In-tree custom driver | Any OAuth2 server with a user profile endpoint returning `{id, email, name}` JSON. Useful to delegate to an existing SaaS backend. |
| **Paymenter** | In-tree custom driver | Open-source billing platform (paymenter.org). Acts as a canonical IdP. Single base URL configuration; `/oauth/authorize`, `/api/oauth/token`, `/api/me` are derived. |

Providers are split into two categories:

- **Canonical IdPs** (Shop, Paymenter): act as primary identity sources — they auto-create local users on first login, sync the user's email into Pelican, and may surface a register URL on the login page. **Only one canonical IdP can be active at a time.** Filament blocks saving when both are toggled on.
- **Social providers** (Google, Discord, LinkedIn): sign-in only. When a canonical IdP is enabled, social providers cannot create new users — they're for users who already exist (e.g. registered on Shop or Paymenter), giving them an SSO shortcut.

### Adding a provider

1. Create an OAuth application on the provider's side (Google Cloud Console,
   Discord Developer Portal, LinkedIn Developers, etc.). Use the **redirect
   URI** that Peregrine displays next to each provider in the admin page —
   exact match is required.
2. Open **Admin → Settings → Auth & Security**, expand the provider's
   section, paste your Client ID and Client Secret, flip the enable toggle,
   save.
3. Reload the login page. Your new button appears.

Client secrets are encrypted at rest with Laravel's app key.

### Custom OAuth2 server (the "Shop" provider)

The generic "Shop" provider lets you delegate login to any OAuth2-compliant
server — for example an existing SaaS that already owns your user accounts.
Configure four URLs in its settings section:

- **Authorize URL** — where users are redirected to consent
- **Token URL** — where Peregrine exchanges the code for an access token
- **User profile URL** — returns `{id, email, name}` JSON for the Bearer
  token
- **Redirect URI** — read-only, copy into your OAuth application on the
  server side

Peregrine treats the Shop as a *canonical* identity provider when it's
enabled: local registration can be closed so user accounts can only originate
from the Shop, and users can still sign in via any linked social provider
(same email).

### Paymenter (open-source canonical alternative)

[Paymenter](https://paymenter.org/) is a Laravel + Filament-based open-source
billing platform with a built-in OAuth2 server (Laravel Passport). It plays
the same role as the Shop in Peregrine, for self-hosted installations that
don't use the BiomeBounty Shop. Setup steps:

1. In your Paymenter admin, go to **OAuth Clients → Create OAuth Client**.
   - Application name: anything (e.g. "Peregrine").
   - Redirect URL: copy the redirect URI shown in the Peregrine
     `Auth & Security` page next to Paymenter — exact match required.
2. Paymenter shows a Client ID + Client Secret — paste them in Peregrine,
   along with your Paymenter base URL (e.g. `https://billing.example.com`),
   then enable the toggle and save.
3. The driver derives `/oauth/authorize`, `/api/oauth/token`, and `/api/me`
   from the base URL. The OAuth flow uses the `profile` scope (the only one
   Paymenter exposes today).

Paymenter user attributes (`first_name`, `last_name`, `email`,
`email_verified_at`) are mapped onto Peregrine users automatically. The
`email_verified_at` timestamp gates auto-linking by email — a Paymenter user
who has not confirmed their email cannot sign into Peregrine.

Shop and Paymenter are **mutually exclusive** — Filament blocks saving when
both are enabled. Pick whichever fits your install.

#### Provisioning game servers from Paymenter purchases

The Peregrine integration documented here covers **identity only** — users
sign into Peregrine via Paymenter SSO. To actually **create / suspend /
terminate game servers** from Paymenter purchases, install on the Paymenter
side:

1. The **[Pelican-Paymenter extension](https://builtbybit.com/resources/pelican-paymenter.63526/)** —
   the server provisioning bridge that turns Paymenter products into Pelican
   servers (handles install, suspend, unsuspend, terminate via Pelican
   Application API).
2. Enable **bridge mode** on the extension.
3. Enable **webhooks** so lifecycle events (purchase, renewal, cancellation)
   reach Pelican in real time.

Without this extension, Paymenter only provides login — it can't push server
specs to Pelican on its own. The Peregrine-side bridge module that mirrors
this billing flow into Peregrine's own server table is planned (see "P3 —
Bridge plugin" in `CLAUDE.md`); for now, configure provisioning entirely on
the Paymenter side.

### Account linking by email

When a user logs in via an OAuth provider for the first time and no local
account matches, behavior depends on the canonical IdP state:

- **No canonical IdP enabled** — Peregrine creates a fresh local account and
  links the identity (regardless of which provider).
- **Canonical IdP enabled (Shop or Paymenter)** — Peregrine refuses the
  creation and prompts the user to register on the canonical IdP first,
  then return to Peregrine. Social providers (Google/Discord/LinkedIn) are
  sign-IN only in this mode. The canonical IdP itself bypasses this rule —
  it IS the sign-up channel.

When a local account already exists with the same email, Peregrine auto-links
the identity **only if the provider has marked the email as verified**
(Google `email_verified`, Discord `verified`, LinkedIn `email_verified`,
Paymenter `email_verified_at`, Shop implicitly trusted). Otherwise the login
is rejected with a message telling the user to sign in with their primary
method, then link the provider from their profile page. This prevents account
takeover via an attacker-controlled provider account using someone else's
email.

### Email collision on canonical login

When a canonical IdP login arrives with a new email (the user changed it on
the IdP side), Peregrine attempts to propagate the change to the local user
row + the corresponding Pelican account. If another local user already owns
that email (typical when a duplicate account exists), the sync is **skipped
gracefully** — login still succeeds with the user's old local email, and a
warning is logged so an admin can merge the duplicate. This keeps users from
being locked out by a stale duplicate row.

## Two-factor authentication

Any user can enable 2FA from **Profile → Security**. The flow:

1. Scan a QR code with any TOTP app (Google Authenticator, Authy, 1Password,
   Microsoft Authenticator, …).
2. Enter the 6-digit code to confirm.
3. Save the 8 one-time recovery codes displayed — they're the only way back
   in if the authenticator app is lost.

On the next login, after a successful password (or OAuth) step, the user
lands on a challenge page that asks for the 6-digit code. A "Use a recovery
code" link lets them fall back to one of the saved codes — each consumed
code is immediately invalidated.

Recovery codes can be regenerated at any time from the same profile page —
generation invalidates all previous codes.

### How the challenge state is stored

Between the password/OAuth step and the TOTP challenge, Peregrine stores a
short-lived pending state in Redis (5 minute TTL) keyed by a UUID. The
browser doesn't receive a session cookie until the challenge succeeds —
this is intentional, it keeps the flow safe across multiple browser tabs and
after an SPA reload.

If the user takes too long or fails too many times, they're bounced back to
the login form.

## Forcing 2FA for admins

Toggle **Require 2FA for admins** in the same admin page.

When on:

- Any admin without 2FA configured who tries to hit an admin endpoint gets a
  **403** response with a redirect URL pointing to `/2fa/setup?enforced=1`.
- The frontend HTTP interceptor picks up this response and automatically
  navigates the browser to the enforced setup page, so admins don't see a
  raw error — they land on a focused page that tells them "your
  administrator requires 2FA" and walks them through setup.
- Once configured, the admin can access `/admin` and the admin API routes.

**Warning**: the Filament save action refuses to apply the toggle if *you*
(the currently signed-in admin) don't have 2FA yet — otherwise you'd lock
yourself out of your own panel on the next request.

## Linked accounts

From **Profile → Security → Linked sign-in providers**, users can:

- See which providers are currently linked (with the provider-side email)
- Link a new provider by clicking "Link" (redirects through the OAuth flow)
- Unlink a provider with the "Unlink" button

The unlink button is automatically disabled if removing it would leave the
user with no way to sign in — i.e. when the user has no password set AND
only one linked identity. Either set a password first or link a second
provider before unlinking.

When an admin disables a provider in the Auth & Security page, Peregrine
counts how many users rely on that provider as their *sole* sign-in method
and blocks the save with a warning if that count is non-zero. An explicit
"I understand the risk" toggle in the Safety section lets the admin
override, but never silently.

## Custom email templates

All security-related notifications are editable from **Admin → Settings →
Email Templates** with subject + HTML body in English and French:

- 2FA enabled
- 2FA disabled
- Recovery codes regenerated
- OAuth provider linked
- OAuth provider unlinked

Variables available in every template:

- `{name}` — user's display name
- `{server_name}` — the panel's configured name (same as your app name)
- `{timestamp}` — when the event happened (server timezone)
- `{ip}` — IP address of the request
- `{user_agent}` — browser/device identifier (truncated)
- `{manage_url}` — link to the user's security settings page

OAuth link/unlink templates also expose `{provider}` with the human-readable
provider name (localized to the user's language).

Leaving a field blank or identical to the default keeps the built-in copy —
the admin override only kicks in when the content actually differs. A
"Reset to defaults" button clears all overrides in one click.

## Related

- [Configuration](configuration.md) — env vars and settings overview
- [Plugins](plugins.md) — extending Peregrine with your own modules
