# WHMCS OAuth — Setup guide

This page walks through wiring your WHMCS install as a **canonical
OpenID Connect identity provider** for Peregrine. Once configured, your
customers click *Sign in with WHMCS* on the Peregrine login page, get
redirected to your WHMCS billing system, authenticate there, and come
back to Peregrine with a session — no second password to maintain.

> ℹ️ This page covers **authentication only** (login / SSO). It is independent
> from the *Bridge — webhook orchestrator* mode that mirrors Pelican server
> state from a WHMCS-driven workflow. You can run either, both, or neither.
> See `/docs/bridge-webhook-orchestrator` for the provisioning side.

## How it works

WHMCS ships a built-in OpenID Connect identity provider since version 8.5
(*Configuration → System Settings → OpenID Connect*). It exposes the
standard OIDC endpoints under `/oauth/*` and supports the standard
`openid profile email` scopes. Peregrine consumes them with a custom
Socialite driver (`WhmcsSocialiteProvider`) — same pattern already used
for Paymenter and the Shop OAuth providers.

```
   1. Customer clicks "Sign in with WHMCS"
                  │
                  ▼
   /api/auth/social/whmcs/redirect (Peregrine)
                  │
                  ▼
   <whmcs>/oauth/authorize.php  (WHMCS shows its login page)
                  │
                  ▼
   /api/auth/social/whmcs/callback (Peregrine, with auth code)
                  │
                  ├─► <whmcs>/oauth/token.php       (exchange code → access token)
                  ▼
   <whmcs>/oauth/userinfo.php (fetch sub / email / name)
                  │
                  ▼
   Peregrine session  ✓  (auto-creates the local user on first login)
```

## Prerequisites

| Component | Minimum version | Why |
|---|---|---|
| WHMCS | 8.5+ | Built-in OpenID Connect identity provider |
| HTTPS on WHMCS | required | OIDC mandates SSL for all OAuth flows |
| Peregrine | current | Ships the `whmcs` Socialite driver |

## 1. Generate WHMCS OpenID credentials

In your WHMCS admin :

1. Open **Configuration → System Settings → OpenID Connect**.
2. Click **Generate New Client API Credentials**.
3. Fill in :
   - **Name** : `Peregrine SSO` (or anything that helps you identify it).
   - **Description** : `Single sign-on for the Peregrine game panel`.
   - **URL** : the public URL of your Peregrine install.
   - **Authorized redirect URIs** : exactly
     `https://YOUR-PEREGRINE-DOMAIN/api/auth/social/whmcs/callback`
4. Click **Generate Credentials**.
5. WHMCS displays a **Client ID** and a **Client secret**. Copy them
   somewhere safe — the secret is shown only once.

> ⚠️ The redirect URI must match **EXACTLY**, including the trailing
> path. WHMCS does literal string comparison; `https://x.com/cb` and
> `https://x.com/cb/` are not the same URL.

## 2. (Optional) Enable the discovery endpoint

WHMCS exposes its OIDC configuration document at
`/oauth/openid-configuration.php`. Peregrine doesn't need it (the four
endpoint paths are derived from your base URL), but if you'd like
WHMCS to be discoverable by other OIDC clients on the standard
`/.well-known/openid-configuration` path, add this to the WHMCS root
`.htaccess` (Apache) or the equivalent Nginx rewrite :

```apache
RewriteRule ^.well-known/openid-configuration ./oauth/openid-configuration.php [L,NC]
```

## 3. Configure Peregrine

Open Peregrine admin → **Auth & Security** → **WHMCS** tab :

1. Toggle **Enable WHMCS as identity provider** ON.
2. **WHMCS base URL** : the root URL of your WHMCS install (no trailing
   slash). Example : `https://billing.example.com`. Peregrine derives
   `/oauth/authorize.php`, `/oauth/token.php` and `/oauth/userinfo.php`
   from this base — you don't fill them separately.
3. **Client ID** : paste the value from WHMCS step 1.
4. **Client secret** : paste the secret from WHMCS step 1.
5. **Redirect URI** : leave the default
   (`https://YOUR-PEREGRINE-DOMAIN/api/auth/social/whmcs/callback`)
   unless you have a reverse-proxy quirk that requires a different host.
   The value here must match what you typed in WHMCS step 1.
6. **WHMCS register page URL** *(optional)* : if you want a "Create
   account on the billing site" link to surface on the Peregrine login
   page, fill in your WHMCS register URL (e.g.
   `https://billing.example.com/register.php`). Leave empty to keep
   only the local register form.
7. **Custom button logo** *(optional)* : SVG / PNG / JPEG / WebP / ICO,
   square preferred (max 1 MB). Replaces the default icon on the
   "Sign in with WHMCS" button.
8. Click **Save Settings**.

> Mutually exclusive : you can only have **one canonical IdP** active at
> a time. Enabling WHMCS while Shop or Paymenter is also enabled fails
> the form save with a clear error. Disable the other one first.

## 4. Test the flow

1. Log out of Peregrine (or open a fresh incognito window).
2. On the login page, you should see a **Sign in with WHMCS** button
   alongside the local email/password form. The button uses your
   custom logo if you uploaded one.
3. Click it. You're redirected to WHMCS's login page.
4. Enter your WHMCS customer credentials. Approve the OAuth consent
   screen (the first time only).
5. You land back on Peregrine, signed in. If your email didn't match
   any local user, Peregrine auto-creates one (no password — only
   sign-in via WHMCS works for that user).

## 5. Troubleshooting

| Symptom | Diagnosis | Fix |
|---|---|---|
| `redirect_uri_mismatch` from WHMCS | The redirect URI typed in Peregrine doesn't match the one registered in WHMCS | Re-paste both URIs side by side. Watch out for `http` vs `https`, trailing slashes, and host case-sensitivity. |
| 401 from `/oauth/token.php` after auth | Wrong Client ID or Client Secret | Re-paste both fields in Peregrine. The secret is encrypted at rest; an empty save keeps the stored value. |
| `email_verified` is always false | Some WHMCS deployments don't expose the `email_verified` claim | Behaviour by design : Peregrine refuses to auto-link unverified emails to existing local accounts. Either verify the email in WHMCS, or have the user log in via local password first to seed the account. |
| Multiple canonical IdPs error | Both Shop and WHMCS (or Paymenter and WHMCS) are toggled ON | Only one canonical provider can be active. Disable the other. |
| Login button doesn't appear | The frontend caches the provider list for 60 s | Wait, or hard-reload (Cmd+Shift+R). |

## 6. Linking and unlinking

A WHMCS-authenticated user can also have a local password (via the
**Account → Security** page in Peregrine) and additional social
identities (Google, Discord, …). The `oauth_identities` table records
each link so audit/lockout logic stays consistent.

Disabling the WHMCS provider in Peregrine admin keeps the
`oauth_identities.provider = 'whmcs'` rows in DB but blocks new logins
from WHMCS. If a user has only WHMCS as their identity (no password,
no other linked provider), the *Lock-out safety* section warns the
admin before saving — accept the risk explicitly to proceed.

## 7. Backward-compat

The new `whmcs` provider has its own settings keys
(`auth_whmcs_enabled`, `auth_whmcs_config`) and never collides with
existing Shop / Paymenter configurations. Migrating to WHMCS later, or
back, just toggles the radios — no data migration is needed.
