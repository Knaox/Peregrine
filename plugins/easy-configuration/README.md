# Easy Configuration

A Peregrine plugin that adds a **Game configuration** section to a server's
overview page, letting players edit their config files (`server.properties`,
`GameUserSettings.ini`, `config.yml`, …) through a clean Nitrado-style UI —
sliders, toggles, dropdowns — instead of editing raw text.

## Features

- **Configuration editing** — values are read live from the real file on the
  server and written back through Pelican's File Manager API. The *template* is
  a pure render schema: it describes how to display each parameter, never the
  value itself.
- **Copy** — copy a configuration (selected files/parameters) to one or more of
  your other servers running the same egg.
- **Boost** — schedule a temporary multiplication of selected numeric values
  over a date range; servers are stopped and restarted cleanly to apply.

## Templates

Templates are JSON files under `storage/app/easy-config/templates/{id}.json`,
designed to be shared and forked on GitHub. See the admin guide and the JSON
schema (documented in a later phase) for the full structure.

> Detailed admin/user documentation and the published JSON schema are added in
> the polish phase.
