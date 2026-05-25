# phpMyAdmin Integration (`peregrine-phpmyadmin`)

Adds a one-click **Open in phpMyAdmin** button to every server database in the
player UI, with automatic login via phpMyAdmin's `signon` mechanism, scoped to
the clicked database.

> phpMyAdmin is **self-hosted by the admin** — this plugin never bundles it.
> The full setup guide (bilingual FR/EN) lives in the admin settings page.

## How it works

1. User clicks the button → Peregrine fetches the DB credentials from Pelican
   and stores them behind a short-lived, one-shot token (hashed in cache).
2. A new tab opens at `https://<your-pma>/?signon_token=…`.
3. Your phpMyAdmin `peregrine_signon.php` POSTs the token to
   `/api/plugins/peregrine-phpmyadmin/redeem` (guarded by a shared secret + an
   optional IP allowlist), gets the credentials, and logs in.

## Setup

Install + activate the plugin, then open **Manage** (`/admin/pma-settings`):
set the phpMyAdmin URL, copy the generated shared secret, and follow the
embedded **Installation guide** (FR/EN). Use **Test the bridge (curl)** to
verify the redeem endpoint from your phpMyAdmin host, then toggle **Enabled**.

## Security

One-shot tokens, short TTL, token hashed at rest, constant-time shared-secret
check (fails closed when unset), optional IP allowlist, HTTPS required,
rate-limited launches, and an audit ledger (`pma_launch_logs`). Launching is
gated by the `database.view_password` permission.

## Notes

- `icon.svg` is an original placeholder mark; replace it with the official
  phpMyAdmin logo (GPL) + a `LICENSE` file if you redistribute.
- Requires a Peregrine shell exposing the `registerDatabaseRowAction` slot.
