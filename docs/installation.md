# Installation Guide

## Requirements

Before installing Fluent Cart FakerPress, ensure your system meets these requirements:

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Fluent Cart**: Latest version required
- **Memory**: Minimum 256MB (512MB recommended for large datasets)
- **Storage**: 100MB free space for plugin files and sample data

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. **Download**: Get the plugin ZIP file from WordPress.org or GitHub releases
2. **Upload**: Navigate to **Plugins → Add New → Upload Plugin**
3. **Install**: Select the ZIP file and click **Install Now**
4. **Activate**: Click **Activate Plugin** after installation completes

### Method 2: Manual Upload

1. **Download**: Get the plugin ZIP file
2. **Extract**: Unzip the file on your local computer
3. **Upload**: Upload the `fluent-cart-fakerpress` folder to `/wp-content/plugins/`
4. **Activate**: Go to **Plugins** in WordPress admin and activate

### Method 3: Composer (For Developers)

```bash
composer require mralaminahamed/fluent-cart-fakerpress
```

## Post-Installation Setup

### 1. Verify Dependencies

The plugin will automatically check for Fluent Cart. If missing:
- Install and activate Fluent Cart plugin
- Refresh the plugins page
- Fluent Cart FakerPress should now be available

### 2. Initial Configuration

1. Navigate to **FC FakerPress** in the WordPress admin menu
2. The plugin will automatically download sample data on first visit
3. Configure your preferences in the settings panel

### 3. Development Setup (Optional)

For development or advanced customization:

```bash
# Install Node.js dependencies
npm install

# Install PHP dependencies
composer install

# Build assets for production
npm run build
```

## Troubleshooting

### Common Issues

**Plugin won't activate:**
- Ensure Fluent Cart is installed and active
- Check PHP version compatibility
- Verify file permissions

**Admin menu not visible:**
- Confirm user has `manage_options` capability
- Check for JavaScript errors in browser console
- Clear WordPress cache if using caching plugins

**Generation fails:**
- Check PHP memory limits
- Verify database permissions
- Ensure Fluent Cart tables exist

### Debug Mode

Enable WordPress debug mode for detailed error logging:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Getting Help

- Check the [FAQ](https://github.com/mralaminahamed/fluent-cart-fakerpress/wiki/FAQ)
- Review [GitHub Issues](https://github.com/mralaminahamed/fluent-cart-fakerpress/issues)
- Contact support via the WordPress.org forums

## Updating

### Automatic Updates

The plugin supports automatic updates through WordPress.org.

### Manual Updates

1. **Backup**: Always backup your database before updating
2. **Deactivate**: Deactivate the plugin in WordPress admin
3. **Replace Files**: Upload new files via FTP/SFTP
4. **Activate**: Reactivate the plugin
5. **Verify**: Test functionality with small data generation

## Uninstallation

### Complete Removal

1. **Deactivate**: Deactivate the plugin in WordPress admin
2. **Delete**: Click **Delete** to remove all plugin files
3. **Clean Database**: Manually remove generated test data if needed

### Data Cleanup

Generated test data can be removed through:
- WordPress admin (individual items)
- Bulk delete tools
- Custom database queries (advanced users only)

**⚠️ Warning**: Always backup before removing generated data.