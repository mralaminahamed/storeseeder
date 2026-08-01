# Installation Guide

## Requirements

- **WordPress**: 6.5 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **A supported e-commerce platform**, active. StoreSeeder writes through a platform driver
  rather than to one fixed plugin. Fluent Cart and WooCommerce ship today; EasyCommerce
  and StoreEngine are planned, and a third party can register their own.

StoreSeeder will **activate without any platform installed** — it declares no `Requires
Plugins` header, deliberately, so it can be installed on a site running whichever platform you
use. What a missing platform costs you is the admin menu and the REST routes, which stay
hidden until at least one driver reports itself active. An admin notice names the platforms you
could install.

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. **Download**: Get the plugin ZIP file from WordPress.org or GitHub releases
2. **Upload**: Navigate to **Plugins → Add New → Upload Plugin**
3. **Install**: Select the ZIP file and click **Install Now**
4. **Activate**: Click **Activate Plugin** after installation completes

### Method 2: Manual Upload

1. **Download**: Get the plugin ZIP file
2. **Extract**: Unzip the file on your local computer
3. **Upload**: Upload the `storeseeder` folder to `/wp-content/plugins/`
4. **Activate**: Go to **Plugins** in WordPress admin and activate

### Method 3: From source

The repository is not published to Packagist, and the plugin declares no
`composer/installers` dependency, so `composer require` will not place it in
`wp-content/plugins/`. Clone it instead:

```bash
cd wp-content/plugins
git clone https://github.com/mralaminahamed/storeseeder.git
cd storeseeder
composer install
yarn install
yarn build          # the admin is a compiled bundle; without this the page is blank
```

`build/` is not committed, so a source checkout must be built before the admin will render.

## Post-Installation Setup

### 1. Choose where data goes

Open **StoreSeeder** in the admin menu.

- **One platform active** — it is selected automatically. Nothing to do.
- **More than one active** — the topbar gains a target selector, defaulting to `Auto`. With
  several platforms present there is no safe default, so the generator page will ask which
  store to write to before it will run. The choice is site-wide and persists.

A generator the chosen platform cannot represent is dimmed and says why, naming the plugin
that would enable it where one would.

### 2. Sample data (optional)

Generators work immediately from built-in defaults. For richer, locale-specific content
StoreSeeder can download reference data from GitHub — but only after you accept a one-time
consent prompt shown on the admin page. Declining costs no functionality, and the decision can
be changed from **Settings** at any time. See
[external-services.md](external-services.md) for exactly what is requested and when.

### 3. Generation defaults (optional)

**Settings** holds the default count, faker locale, seed, and metadata toggle. These are stored
in your browser, not in the database, so they are per-person rather than per-site.

## Troubleshooting

**No StoreSeeder menu item**
- No supported platform is active — check the admin notice, which lists them
- Confirm your user has the `manage_options` capability

**Admin page is blank**
- Built from source without running `yarn build`
- Check the browser console for JavaScript errors

**"Choose where to write" will not go away**
- More than one platform is active and none has been chosen. Pick one in the topbar or on the
  generator page; it is stored site-wide.

**Generation fails**
- Read the error message: generators that build on others say so explicitly, e.g. orders need
  products and customers first, refunds need charge transactions
- Check PHP memory limits and that the platform's own tables exist

### Debug Mode

Enable WordPress debug logging for detailed errors:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

StoreSeeder writes structured entries to `wp-content/debug.log` when `WP_DEBUG_LOG` is on.
There is no plugin-specific debug constant.

### Getting Help

- [GitHub Issues](https://github.com/mralaminahamed/storeseeder/issues)
- [SUPPORT.md](../SUPPORT.md) — where to ask, what to include, what is out of scope

## Updating

1. **Backup** the database before updating
2. Update through **Plugins → Updates**, or replace the plugin folder
3. **Verify** with a small generation run

## Uninstallation

1. **Deactivate** the plugin in WordPress admin
2. **Delete** it to remove the plugin files

Deleting the plugin does **not** remove data it generated, the
`storeseeder_sample_data_consent` and `storeseeder_target_platform` options, or the downloaded
sample data in `wp-content/uploads/`. There is no uninstall routine — generated records are
indistinguishable from real ones by design, so removing them automatically would risk deleting
data you wanted.

Remove generated data through your platform's own admin screens or bulk delete tools.

> [!WARNING]
> Always back up before removing generated data.
