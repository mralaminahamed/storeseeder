# StoreSeeder — WordPress.org Submission Form Answers

Answers for the "Add your Plugin" form at https://wordpress.org/plugins/developers/add/. Every confirmation below is safe to check for this plugin. This file is for reference only and is excluded from the distributed zip.

## Upload

| Field | Value |
|-------|-------|
| Plugin display name | **StoreSeeder** |
| Resulting URL / slug | `https://wordpress.org/plugins/storeseeder` (slug `storeseeder`) |
| Version / Stable tag | 1.0.0 |
| Zip to upload | `release/storeseeder.zip` — **4.3 MB** (limit is 10 MB) |
| Build the zip | `composer release` (from the plugin root) |

Before uploading, confirm the WordPress.org username in `readme.txt` (`Contributors: mralaminahamed`) is the **same account** you are submitting from (`mrabir.ahamed@gmail.com`). If the account's username differs, update the `Contributors` line to match.

---

## Step 1 — Before you submit

- ☑ **I have read the Frequently Asked Questions.**
- ☑ **This plugin complies with all of the Plugins Directory Guidelines.** Security (escaping, input sanitization, capability + nonce), GPL licensing, prefixing, and disclosure of external services and source code were all reviewed — see `plugin-submission-review.md`.
- ☑ **Tested with the Plugin Check plugin, all issues resolved (apart from false positives).** Ran `wp plugin check` on the built `storeseeder.zip`. **Zero real issues.** The only two findings are false positives: `wp_register_ability()` and `wp_register_ability_category()` are flagged as needing WordPress 6.9, but they are wrapped in `function_exists()` guards and only run on the `wp_abilities_api_init` hooks, so they never execute on the declared 6.5 floor when the Abilities API is absent.

---

## Step 2 — Common reasons plugins are rejected

### 🏷️ Naming and ownership (Guideline 17)

- ☑ **The name is not confusingly similar to existing plugins, projects, organizations, or trademarks.** "StoreSeeder" is a distinctive, coined name. The slug `storeseeder` is available (returns no result on WordPress.org), and no existing plugin uses this name. The third-party product "Fluent Cart" appears **only in the description** as the host platform it generates data for — never leading the plugin name.
- ☑ **I have permission to upload this plugin and my WordPress.org account accurately represents the owner (`mrabir.ahamed@gmail.com`).** The plugin is the author's own work. (Ensure the `Contributors` username matches this account, per the Upload note above.)

### 🔓 Trialware (Guidelines 5 and 6)

- ☑ **No artificial limitations to included functionality.** Every generator is fully functional with no paywall, license key, feature gate, trial, or usage cap. The Subscriptions generator notes that *recurring billing* is a Fluent Cart Pro feature, but the generator itself gates nothing — it produces subscription fixture rows unconditionally.

### 🚫 Not-accepted categories

- **Arbitrary code insertion/execution** — Not applicable. No PHP/JS editor, no file manager, no `eval`, no code-generating-and-executing behavior. All output is escaped.
- **Downloading executable code from external sources** — The optional "Sample Data Sync" downloads a zip from a public GitHub repository (`storeseeder-sample-data-fluent-cart`), but its contents are **JSON reference data only** (locale-specific names, addresses, tags) — no PHP, JavaScript, or any executable code. It is administrator-initiated, never runs on activation, and is fully disclosed in the readme `== External services ==` section. (Be ready to point the reviewer to that section under Guideline 8.)
- **Already well represented / no differentiation (Guideline 18)** — StoreSeeder is Fluent-Cart-specific with substantial, non-trivial functionality: 17 generators that write valid records through Fluent Cart's own models, a React single-page admin with live preview and a batch queue, a full REST API, and optional Model Context Protocol (MCP) tooling. This is meaningfully differentiated from generic test-data plugins.

---

## Step 3 — Submission acknowledgement

- ☑ **I understand submissions that do not follow the Guidelines may be rejected, and repeated/serious violations may result in restrictions.**
- ☑ **I understand hosting is provided subject to continued compliance with the Guidelines.**

---

## Reviewer talking points (if asked)

- **External request:** one optional, admin-initiated GitHub download of JSON sample data (disclosed in `== External services ==`); a second browser-side request to `api.wordpress.org` on the "Our Plugins" page. Neither sends site, user, or store data.
- **Source code:** the shipped `build/` bundle is compiled from `src/` (excluded from the zip); the full source and build steps are linked in the readme `== Source code ==` section.
- **Dependency:** requires Fluent Cart, enforced by the `Requires Plugins: fluent-cart` header.

---

## Additional Information (paste into the form's box)

> StoreSeeder makes test data — products, orders, customers, and more — for Fluent Cart stores, for use on dev/staging. It needs the Fluent Cart plugin.
>
> Two things to flag:
>
> - The optional Sample Data Sync downloads a zip of JSON reference data (locale names, addresses, tags) from a public GitHub repo. Data only, no code, admin-triggered. It's listed under External services in the readme.
> - Plugin Check flags `wp_register_ability()` and `wp_register_ability_category()` as needing WP 6.9. They're `function_exists()`-guarded and only run on the abilities-api hook, so they never fire on the 6.5 minimum.
>
> Source is on GitHub (the `build/` bundle is compiled from `src/`), linked in the readme.
