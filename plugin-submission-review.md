# WordPress.org Submission Checklist — StoreSeeder

Audited against the WordPress.org Plugin Directory submission requirements and the 18 plugin guidelines, using **easycommerce-fakerpress** (already published) as the reference. Status as of version **1.0.0**.

Legend: ✅ pass · 🔧 fixed in this pass · ⚠️ action needed

| # | Checklist item | Status | Notes |
|---|----------------|--------|-------|
| 1 | **Plugin name / slug — trademark** | ⚠️ | See "Remaining blocker" below. The name leads with the third-party "Fluent Cart" trademark. |
| 2 | Main file name matches slug (`storeseeder.php`) | ✅ | |
| 3 | Plugin header complete + valid | 🔧 | Fixed `License` → `GPLv2 or later`, `License URI` → `https://www.gnu.org/licenses/gpl-2.0.html`, `Requires at least` → `6.5` (the `Requires Plugins` header needs WP 6.5+), `Version` → `1.0.0`. `Requires Plugins: fluent-cart`, `Text Domain`, `Domain Path` all present. |
| 4 | `readme.txt` header fields | 🔧 | Added `Requires Plugins`, bumped `Requires at least` to 6.5, `Stable tag` → 1.0.0, `License` → `GPLv2 or later`; retuned tags away from generic terms. |
| 5 | `readme.txt` `== External services ==` | 🔧 | **Added.** Discloses the two outbound requests (GitHub sample-data zip; `api.wordpress.org` for the Our Plugins page) with endpoint, trigger, data sent, and ToS/Privacy links. Required by WP.org. |
| 6 | `readme.txt` `== Source code ==` | 🔧 | **Added.** Discloses that `build/` is compiled from `src/` (excluded from the zip) and links the public repo + build steps. Required because a minified bundle ships. |
| 7 | `readme.txt` privacy accuracy | 🔧 | The old "Other Notes" claimed "nothing is transmitted externally" — false given the two services. Rewritten to match the disclosure. |
| 8 | `readme.txt` content accuracy | 🔧 | Corrected "13 generators" → 17, removed the deleted **Locations** entry, added the five new generators, and added a `1.0.0` changelog + upgrade notice. |
| 9 | Version sync (header / constant / stable tag) | 🔧 | All read `1.0.0` (`storeseeder.php`, `STORESEEDER_VERSION`, class `@version`, `readme.txt` Stable tag). POT regenerated. |
| 10 | GPL-compatible license (code + assets) | ✅ | GPLv2-or-later; bundled deps (FakerPHP, faker-picsum) are MIT/GPL-compatible. |
| 11 | Output escaping | ✅ | Only two PHP output sites; both are a static literal and an `esc_html__()`-escaped notice. All UI is React. |
| 12 | Input sanitization | ✅ | No direct `$_GET/$_POST/$_REQUEST/$_SERVER`. All input flows through REST args with `sanitize_callback` + `validate_callback`. |
| 13 | SQL safety | ✅ | No raw `$wpdb`; all data access goes through Fluent Cart's parameterized model query builder. |
| 14 | Capability + nonce | ✅ | Every REST route gates on `current_user_can( 'manage_options' )`; the `wp_rest` nonce is minted for the SPA and enforced by core. No custom AJAX/admin-post handlers. |
| 15 | Prefixing | ✅ | All globals prefixed `storeseeder` / `StoreSeeder` / `STORESEEDER`; 24 hook names verified. |
| 16 | Text domain | ✅ | 289 i18n calls, all `storeseeder`; POT present. |
| 17 | No obfuscation; source available | ✅ | `build/admin.js` is a standard (not obfuscated) webpack bundle; readable `src/` in the repo, disclosed in `== Source code ==`. |
| 18 | Debug code | 🔧 | Wrapped the three `error_log()` calls in `extract_zip()` in a `WP_DEBUG`-guarded `debug_log()` helper. |
| 19 | `phpcs.plugin-review.xml` runs | 🔧 | Was broken — referenced the uninstalled `WordPress-VIP-Go` standard. Replaced with the WPPluginCheck ruleset used by easycommerce-fakerpress. **`composer phpcs:plugin-review` now passes clean (exit 0).** |
| 20 | Clean distribution zip (`.distignore`) | 🔧 | Excludes `node_modules`, `.git`, `tests`, `docs`, `src`, `.github`, `.wordpress-org`, all dev config, and now `.superpowers/` + `.phpunit.result.cache`. `vendor/` is intentionally kept (production FakerPHP). |
| 21 | No external JS/CSS loading (CDN) | ✅ | All assets bundled locally in `build/`; no CDN `<script>`/`<link>`. |
| 22 | No trialware / license gating (Guideline 5) | ✅ | Fully functional, no Pro key checks. Subscriptions note that active billing needs Fluent Cart Pro, but generation itself is unrestricted. |
| 23 | No persistent admin nags (Guideline 11) | ✅ | Only a dependency notice when Fluent Cart is inactive; the "Our Plugins" page is opt-in navigation, not a nag. |
| 24 | Screenshots | ⚠️ (minor) | `readme.txt` lists three screenshot captions but no `screenshot-N.png` files exist in `.wordpress-org/` yet. Not a blocker; add before publishing for a complete listing. Icon + banners are present (redesigned in 1.0.0). |
| 25 | `readme.txt` passes the official validator | ⬜ | Run https://wordpress.org/plugins/developers/readme-validator/ on the final file (structure verified locally; validator run is the last manual step). |

## Remaining blocker — plugin name / slug (Guideline 17)

**"Fluent Cart" is a third-party commercial product/trademark (WPManageNinja).** WordPress.org's naming rule requires a third-party name to appear only *after* a connector (`for`, `with`), never leading the name. "**Fluent Cart** FakerPress" and the slug `storeseeder` both lead with the trademark, which is a common rejection reason.

- This differs from **easycommerce-fakerpress**, where "EasyCommerce" is the *author's own* plugin — allowed.
- Compliant forms would be e.g. **"Test Data Generator for Fluent Cart"** (slug `test-data-generator-for-fluent-cart`). Note "FakerPress" is itself an existing WordPress.org plugin name, so reusing it is also risky.

**This was not auto-fixed** because renaming cascades through the slug, text domain, function/class prefixes, constants, and the main file name — a product/branding decision, not a mechanical edit. Decide on the final name before submitting; everything else on this list is ready.

## What to run before hitting "Submit"

1. `composer lint && composer phpstan && composer phpcs:plugin-review` (all green).
2. `composer zip:dev` or `composer release` and confirm the zip contains `build/`, `vendor/`, `includes/`, `languages/`, `readme.txt`, `storeseeder.php` — and **not** `src/`, `tests/`, `node_modules/`, `.wordpress-org/`.
3. Validate `readme.txt` in the official validator.
4. Add `screenshot-1..3.png` to `.wordpress-org/` (optional but recommended).
5. Resolve the naming decision (item above).
