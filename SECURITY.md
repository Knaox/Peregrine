# Security policy

## Reporting a vulnerability

If you've found a security issue in Peregrine, **please do not open a public GitHub issue**. Public disclosure before a fix is shipped puts every Peregrine operator at risk.

Instead, email the maintainer:

📧 **damienrouge@hotmail.com**

Please include:

- The version of Peregrine you tested (`git rev-parse HEAD` or the GHCR tag).
- Reproduction steps — minimal payload / curl command / scenario.
- The impact you believe the issue has (auth bypass, RCE, data leak, privilege escalation, …).
- Any patch you'd suggest, if you have one.

I aim to acknowledge within **72 hours**, and to ship a fix or a mitigation within **14 days** for critical issues, **30 days** for high/medium ones.

You'll be credited in the release notes (and the commit) unless you'd rather stay anonymous — just say so in your report.

## Scope

In scope:

- The Peregrine codebase in this repository.
- The official Docker image at `ghcr.io/knaox/peregrine`.
- The first-party plugins shipped under `plugins/`.

Out of scope (report upstream instead):

- Vulnerabilities in [Pelican](https://pelican.dev) → report to the Pelican project.
- Vulnerabilities in [Laravel](https://laravel.com), [Filament](https://filamentphp.com), [React](https://react.dev) or any other dependency → report to the upstream project.
- Vulnerabilities in third-party plugins published on `Knaox/peregrine-plugins` → report to the plugin author (see the plugin's repo).

## Supported versions

Only the **latest published release** is supported with security fixes. If you're running an older version, the fix is to upgrade.

| Version | Supported |
|---|---|
| `latest` (current `main`) | ✅ |
| Previous releases | ❌ |
