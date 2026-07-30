# Getting Help

StoreSeeder is maintained by one developer, so a little triage on your side goes a long way.

## Read First

| Question | Where to look |
|----------|---------------|
| How do I install and activate it? | [docs/installation.md](docs/installation.md) |
| How do I run a generator? | [docs/usage.md](docs/usage.md) |
| What can each generator produce? | [docs/features.md](docs/features.md) |
| How is the plugin put together? | [docs/architecture.md](docs/architecture.md) |
| How do I build from source or add a generator? | [docs/development.md](docs/development.md) |
| What changed in this version? | [CHANGELOG.md](CHANGELOG.md) |

## Bugs and Feature Requests

Open an issue on the [issue tracker](https://github.com/mralaminahamed/storeseeder/issues) using the
bug report or feature request template. Include the StoreSeeder, Fluent Cart, WordPress, and PHP
versions, plus the generator and parameters involved — without those, a report usually cannot be
reproduced.

## Security Vulnerabilities

Do **not** open a public issue. Email **alamin.ahamed.dev@gmail.com** with the subject
`[SECURITY] StoreSeeder - Brief Description`. The full process, including response timelines, is in
the [security policy](SECURITY.md).

## Before Reporting

A few checks resolve most reports:

- Fluent Cart is installed **and active** — StoreSeeder cannot activate without it
- You are on a development or staging site, not production
- The plugin was built (`yarn build`) if you installed from source rather than a release zip
- `WP_DEBUG` is on, and you have captured anything relevant from `debug.log` and the browser console

## What Is Not Supported

- Running StoreSeeder on a production store, or recovering data it wrote there — back up before
  generating
- Fluent Cart itself; take core store questions to
  [Fluent Cart support](https://wordpress.org/support/plugin/fluent-cart/)
- Fluent Cart Pro features that generated records depend on, such as active subscription billing
- Custom generator development on your behalf — [CONTRIBUTING.md](CONTRIBUTING.md) documents the
  pattern if you want to build one

## Contributing a Fix

Patches are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, coding standards, and the
quality gates a pull request needs to pass.
