# Contributing to Peregrine

Thanks for taking the time to look at this. Peregrine is open-source under the MIT licence — bug reports, feature requests, plugin proposals and PRs are all welcome.

## Quick links

- 🐛 [Open a bug report](https://github.com/Knaox/Peregrine/issues/new?template=bug_report.yml)
- 💡 [Suggest a feature](https://github.com/Knaox/Peregrine/issues/new?template=feature_request.yml)
- 🔒 [Report a security issue](SECURITY.md) (private — do **not** open a public issue)
- 🧩 [Submit a plugin to the marketplace](https://github.com/Knaox/peregrine-plugins)

---

## Development setup

```bash
git clone https://github.com/Knaox/Peregrine.git && cd Peregrine
composer install
pnpm install
pnpm run dev            # Vite HMR on :5173
php artisan serve       # PHP on :8000
php artisan queue:work  # emails + sync jobs
```

If you'd rather use Docker for the dev loop, see [`docker/dev/README.md`](docker/dev/README.md).

---

## Code conventions (enforced at review)

### TypeScript / React

- **Strict TypeScript**, never `any` — use `unknown` + type guards if you must.
- **Files ≤ 300 lines**. If it grows past that, split.
- One component = one file = one responsibility. Functional components only.
- Props in their own `.props.ts` file (e.g. `ServerCard.props.ts`).
- Hooks in `resources/js/hooks/`, services in `resources/js/services/`.
- Animations via CSS only (transitions / keyframes), no animation library.
- Imports use the `@/` alias — no `../../../`.
- No `console.log` in committed code. No `eslint-disable` without an explaining comment. Never `@ts-ignore`.

### Laravel / Backend

- Thin controllers — logic lives in Services (`app/Services/`).
- Validation in Form Requests, responses via API Resources, auth via Policies.
- Type controller return types (Scramble uses them to auto-generate the OpenAPI doc).
- One migration = one table or one modification.
- Long jobs (Pelican API calls, emails) go through the queue.
- **Plugin Mailables must never be queued directly** — dispatch via `App\Jobs\SendPluginMail`. See `CLAUDE.md` § "Plugin mail / jobs — contrat queue-safe".

### i18n — non-negotiable

- **Never hardcode user-facing strings.** Every string goes through `react-i18next` (frontend) or Laravel translations (backend).
- **EN and FR are always updated in the same commit.** A PR that adds a string in `en.json` without `fr.json` will be sent back.
- Snake_case keys, English, hierarchical: `servers.status.active`, `bridge.sync.success`.
- API errors return i18n keys (`{"error": "servers.not_found"}`), the frontend translates.

### Commits

- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`.
- One commit = one logical change.
- English commit messages.
- No version bump unless explicitly asked.

---

## Pull requests

1. Branch off `main`. Use a descriptive branch name (`feat/theme-marketplace`, `fix/console-scroll-overflow`).
2. Run the tests locally: `php artisan test` and `pnpm run type-check`.
3. Keep the PR focused — one feature or fix per PR.
4. Fill the PR template (it'll show up automatically when you open the PR).
5. Screenshots for any UI change. Before/after if you're modifying an existing page.

---

## Adding a language

1. Copy `resources/js/i18n/en.json` → `resources/js/i18n/<lang>.json` and translate.
2. Copy `lang/en/` → `lang/<lang>/` and translate.
3. Register the language in `resources/js/i18n/index.ts`.
4. Add an entry to the language picker in the Setup Wizard and the user profile page.
5. Open a PR. Native speakers strongly preferred for new languages.

---

## Submitting a plugin

Peregrine's plugin marketplace lives at [`Knaox/peregrine-plugins`](https://github.com/Knaox/peregrine-plugins).

1. Build your plugin against the public plugin API (see [`docs/plugins.md`](docs/plugins.md) and the reference implementation in [`plugins/invitations/`](plugins/invitations/)).
2. Publish a release with the bundled `.zip` as a release asset (see [release workflow notes in `docs/plugins.md`](docs/plugins.md)).
3. Open a PR on `Knaox/peregrine-plugins` adding your plugin to `registry.json`.

---

## Code of conduct

Be kind. Don't be a jerk. We don't have a long formal CoC yet — assume good faith, give feedback on the code and not on the person, and we'll work something out.
