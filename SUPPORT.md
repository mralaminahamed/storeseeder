# Getting Help

StoreSeeder is maintained by one developer, so a little triage on your side goes a long way.

## Read First

| Question | Where to look |
|----------|---------------|
| How do I install and activate it? | [docs/guides/installation.md](docs/guides/installation.md) |
| How do I run a generator? | [docs/guides/usage.md](docs/guides/usage.md) |
| What can each generator produce? | [docs/guides/features.md](docs/guides/features.md) |
| How is the plugin put together? | [docs/guides/architecture.md](docs/guides/architecture.md) |
| How do I build from source or add a generator? | [docs/guides/development.md](docs/guides/development.md) |
| What changed in this version? | [CHANGELOG.md](CHANGELOG.md) |

## Bugs and Feature Requests

Open an issue on the [issue tracker](https://github.com/mralaminahamed/storeseeder/issues) using the
bug report or feature request template. Include the StoreSeeder, WordPress, and PHP versions, which
e-commerce platform you are writing to and its version, plus the generator and parameters involved —
without those, a report usually cannot be reproduced.

## Security Vulnerabilities

Do **not** open a public issue. Email **alamin.ahamed.dev@gmail.com** with the subject
`[SECURITY] StoreSeeder - Brief Description`. The full process, including response timelines, is in
the [security policy](SECURITY.md).

## Before Reporting

A few checks resolve most reports:

- A supported e-commerce platform is installed **and active** — Fluent Cart, today. StoreSeeder
  activates without one and says so on its own screen, so "nothing generates" usually means no
  platform is active
- With more than one platform active, a target has been chosen — the generator page asks, and both
  run actions stay disabled until it is answered
- You are on a development or staging site, not production
- The plugin was built (`yarn build`) if you installed from source rather than a release zip
- `WP_DEBUG` is on, and you have captured anything relevant from `debug.log` and the browser console

## What Is Not Supported

- Running StoreSeeder on a production store, or recovering data it wrote there — back up before
  generating
- The e-commerce platform itself; take core store questions to its own support — for Fluent Cart,
  [Fluent Cart support](https://wordpress.org/support/plugin/fluent-cart/)
- Paid platform features that generated records depend on, such as active subscription billing on
  Fluent Cart Pro
- Third-party platform drivers registered through `storeseeder_platforms` — report those to whoever
  ships the driver
- Custom generator development on your behalf — [CONTRIBUTING.md](CONTRIBUTING.md) documents the
  pattern if you want to build one

## Contributing a Fix

Patches are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, coding standards, and the
quality gates a pull request needs to pass.
