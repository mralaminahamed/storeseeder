---
title: Support & troubleshooting
description: Where to ask, what to include, and the things that most often look like bugs.
---

## Where to ask

| For | Go to |
|---|---|
| A bug, or something that behaves differently from these pages | [GitHub issues](https://github.com/mralaminahamed/storeseeder/issues) |
| A question about using it | [WordPress.org support forum](https://wordpress.org/support/plugin/storeseeder/) |
| A new shop type, or a locale for an existing one | [storeseeder-recipes issues](https://github.com/mralaminahamed/storeseeder-recipes/issues) |
| Something you would rather not post publicly | [A private security advisory](https://github.com/mralaminahamed/storeseeder/security/advisories/new) |

## What to include in a bug report

StoreSeeder is built so that a run can be reproduced exactly, which makes a good report short. Five
things:

1. **The seed**, if you set one. With it, whoever reads the report gets the data you got.
2. **The locale and the count.**
3. **The target platform**, and its version. Settings → Target platform names both.
4. **Which store plugins are active.** Half of what looks like a StoreSeeder bug is two stores active
   and `Auto` resolving to the other one.
5. **StoreSeeder's version**, from Settings → About.

If a run failed, the error text as it appeared. It is passed through from the endpoint rather than
replaced with a generic message, so it usually names the parameter or the resource at fault.

```
StoreSeeder 1.2.0 · WooCommerce 10.9.4 · also active: Fluent Cart 1.6.0
Products, count 250, locale fr_FR, seed 42
"Invalid parameter(s): price_range"
```

## Things that are working as intended

### The Generate button is disabled and nothing says why

Hover it. More than one store is active and no target is chosen, so `Auto` will not guess — writing
rows into the wrong store is the one failure nobody notices afterwards. Pick one in the topbar.

See [Choosing a target](/storeseeder/getting-started/target-platform/).

### A generator is dimmed

Your target platform cannot write that resource, and the reason is on the tile. There are two kinds and
they mean different things: **unsupported** is a concept the platform does not have, and **missing
extension** names the plugin that would enable it.

WooCommerce, for instance, records payment on the order itself, so there is no separate transaction
record to create and no plugin changes that. Subscriptions, by contrast, are one plugin away.

See [Platform support](/storeseeder/reference/platform-support/).

### A count in a recipe is struck through

Same reason, said before the run rather than after it. The alternative would be promising nine hundred
transactions and producing zero.

### Products are all called "Premium Widget"

The sample-data archive has not been downloaded, so generators are falling back to the handful of
built-in nouns. Settings → Sample data → **Sync now**, and accept the consent prompt.

If it *is* synced and names are still generic, try **Force re-sync**: a plain re-sync used not to
overwrite existing files, so a changed archive could leave stale ones in place.

### Product names are English in a non-English locale

If a recipe is selected, check its card. **"Product names stay en_US"** means that recipe ships no
vocabulary for your locale — names, addresses and phone numbers will still be local, because those come
from FakerPHP; the product titles come from English.

### A recipe run stops partway

It reports which step failed and keeps what the earlier steps wrote. Fix whatever the error names, then
**Undo this recipe** and run it again — the undo is scoped to that run's id, so it will not touch
anything else.

### The dashboard counts do not match the store

They are stored in your browser, not on the server, so another browser has its own and a cleared
browser has none. The [danger zone](/storeseeder/guides/cleanup/)'s row counts come from the ledger on
the server and are the authoritative ones.

## Things that are genuinely wrong

### A parameter appears to do nothing

That is a bug, and a specific one this project takes seriously: a control that moves, saves and changes
nothing. Every parameter in the admin, the REST schema and the MCP tools is meant to reach the generator
or a writer, and where one cannot be honoured it is removed rather than left looking functional.

Report it with the parameter name and the values you set. If the *preview* also ignores it, say so —
that narrows it considerably.

### A run of more than 100 fails with "Invalid parameter(s): count"

Fixed in 1.2.0. The endpoint caps a single request at 100 rows and rejects anything larger during
argument validation, and the admin sent whatever the count stepper said — while the stepper allowed up
to 100,000. So a run of 250 failed before writing anything. Larger counts are split into requests of a
hundred now, and the progress bar counts them.

On 1.1.0, keep a single run at 100 or use a recipe, which has always split its plan correctly.

### Deletion left rows behind

Check the danger zone's message rather than the count. Two cases are expected:

- Rows created **before** the ledger existed are not in it and cannot be removed automatically.
- A writer that cannot delete a resource keeps its ledger row deliberately — forgetting it would leave
  the row in your store with nothing left that knows StoreSeeder created it.

Anything else is worth reporting.

## What is not supported

- **Running StoreSeeder on a production store**, or recovering data it wrote there. It is a development
  tool: use it on development and staging sites, and back up before a large run.
- **The e-commerce platform itself.** Take core store questions to its own support —
  [Fluent Cart](https://wordpress.org/support/plugin/fluent-cart/),
  [WooCommerce](https://wordpress.org/support/plugin/woocommerce/).
- **Paid platform features that generated records depend on**, such as active subscription billing.
- **Third-party platform drivers** registered through `storeseeder_platforms`. Report those to whoever
  ships the driver; the [extension points](/storeseeder/reference/extension-points/) are documented, the
  drivers built on them are not ours.
- **Writing a generator on your behalf.**
  [CONTRIBUTING.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/CONTRIBUTING.md) documents
  the pattern if you want to build one, and patches are welcome.

## Security

Do not open a public issue for a vulnerability. Use a
[private advisory](https://github.com/mralaminahamed/storeseeder/security/advisories/new); the process
and what is in scope are in
[SECURITY.md](https://github.com/mralaminahamed/storeseeder/blob/trunk/SECURITY.md).

## Before you file

Two questions that resolve most reports on their own:

- **Does the preview show the same problem as the run?** The preview uses the same code path, so if
  they disagree that is itself the finding, and if they agree the problem is upstream of the writer.
- **Does it happen with a fixed seed?** If yes, the report becomes reproducible for everyone. If it
  happens only sometimes, say that too — it points at ordering or at an existing row being drawn.
