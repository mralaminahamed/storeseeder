# e2e must-use plugins

PHP fixtures the browser suite needs the *site* to have, rather than the browser. Each file here is symlinked into `wp-content/mu-plugins` for the run and unlinked afterwards, so the install is left
as it was found and an edit here takes effect on the next run without copying anything.

| File                                   | What it adds                                                                      | Used by                         |
|----------------------------------------|-----------------------------------------------------------------------------------|---------------------------------|
| `zz-storeseeder-e2e-stub-platform.php` | A second platform driver, `stub-cart`, registered through `storeseeder_platforms` | `specs/platform-picker.spec.ts` |

Point `STORESEEDER_E2E_MU_DIR` at the install's `wp-content/mu-plugins` to enable the specs that need them; without it those specs skip rather than fail, because a checkout has no WordPress to link
into.

```bash
STORESEEDER_E2E_MU_DIR=/path/to/wp-content/mu-plugins yarn test:e2e
```

Two conventions worth keeping:

- **`zz-` prefix.** must-use plugins load alphabetically and before regular plugins, so a fixture that hooks StoreSeeder has to sort last and check `class_exists()` before touching anything — the
  plugin may be deactivated while the link is still in place, and a fixture that assumed otherwise would fatal the admin instead of quietly not registering.
- **Real files, not strings.** These started life as an escaped heredoc inside the spec, where
  `\\\\StoreSeeder\\\\Platforms` was four backslashes deep and nothing could lint it. A file gets `php -l`, an editor, and a reviewable diff.
