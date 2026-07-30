# StoreSeeder Documentation

Generate realistic test data for Fluent Cart stores. Start with the [project README](../README.md)
for the overview; these pages go deeper.

## Using StoreSeeder

| Page | What it covers |
|------|----------------|
| [Installation](installation.md) | Requirements, install from a release zip or from source, activation with Fluent Cart |
| [Usage](usage.md) | Running generators, live preview, batch queue, settings, and run history |
| [Features](features.md) | The 17 generators and what each one writes into the store |

## Building on StoreSeeder

| Page | What it covers |
|------|----------------|
| [Architecture](architecture.md) | Request flow from the React admin through REST controllers, generators, and Fluent Cart models |
| [Development](development.md) | Local setup, build and test commands, coding standards, adding a generator, release process |

## Project

| Page | What it covers |
|------|----------------|
| [Changelog](../CHANGELOG.md) | Full version history in Keep a Changelog format — the canonical record |
| [Contributing](../CONTRIBUTING.md) | Branching, conventional commits, quality gates, pull request expectations |
| [Code of Conduct](../CODE_OF_CONDUCT.md) | Contributor Covenant 2.1 |
| [Support](../SUPPORT.md) | Where to ask, what to include, what is out of scope |
| [Security](../SECURITY.md) | Private vulnerability reporting and the plugin's security measures |
| [External Services](external-services.md) | The two outbound requests the plugin can make, what they send, and how to opt out |

## Reference

- REST API — every generator at `storeseeder/v1/<resource>/generate`, plus a read-only preview route;
  all endpoints require the `manage_options` capability
- Hooks — filters and actions across the generation lifecycle, described in
  [architecture.md](architecture.md) with copy-paste examples in the
  [README](../README.md#extensibility)
- [`readme.txt`](../readme.txt) — WordPress plugin directory metadata; together with the plugin header
  it is the source of truth for version and compatibility numbers

> [!WARNING]
> StoreSeeder writes large volumes of fake data directly into your store. Use it on development or
> staging sites only, and back up the database before generating large datasets.
