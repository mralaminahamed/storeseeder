=== Fluent Cart FakerPress ===
Contributors: mralaminahamed
Tags: ecommerce, faker, data-generation, testing, development
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic Fluent Cart test data: smart generators, real-time validation, advanced config, and seamless admin integration.

== Description ==

Fluent Cart FakerPress is a robust WordPress plugin designed to generate realistic test data for the Fluent Cart e-commerce platform. It supports developers, agencies, and store owners in creating sophisticated datasets for testing, demonstrations, and performance evaluation. Key features include:

* **Smart Generators**: For products, customers, orders, coupons, and more.
* **Real-Time Validation**: Ensures data integrity with dependency checks and user-friendly feedback.
* **Advanced Configuration**: Nested parameters, intelligent defaults, and extensible hooks.
* **Comprehensive Hook System**: Filters and actions for complete data customization and workflow integration.
* **Modern Interface**: Built with React Router v7, Tailwind CSS, and automatic WordPress admin color scheme adaptation.
* **Enterprise-Grade Architecture**: PSR-4 compliant, with native Fluent Cart model integration.

This plugin is ideal for enterprise development, QA testing, integration validation, and scalable performance assessments in non-production environments.

**Extensibility & Customization**:
The plugin provides a comprehensive hook system allowing developers to customize every aspect of data generation:
- **Data Filters**: Modify generated data before creation with filters
- **Result Filters**: Customize returned data with result filters
- **Workflow Actions**: Integrate with generation process using strategic actions
- **API Integration**: Filter REST responses with complete API customization

**Data Generation Highlights**:
- **Products**: Includes attributes, pricing strategies, and inventory tracking.
- **Customers**: Features demographics, purchase history, and behavioral segmentation.
- **Orders**: Covers payment processing, shipping calculations, and fulfillment workflows.
- **Coupons**: Supports discount rules, usage limits, and targeting logic.
- **Attributes**: Generates product attribute groups and taxonomy terms.
- **Refunds**: Creates refund transactions against existing orders.
- **Logs**: Generates activity log entries for audit trails.

Generated data leverages the Faker library for authenticity while adhering to real-world e-commerce patterns, ensuring compatibility with Fluent Cart updates and extensions.

**Model Context Protocol (MCP) Integration**:
The plugin supports optional MCP integration for advanced AI-assisted data generation workflows. This feature requires the WordPress Abilities API (bundled in WordPress 6.9+, or installable separately) and the `mcp-adapter` plugin. The MCP integration is completely optional and degrades gracefully—the plugin functions normally without these dependencies.

== Installation ==

### Automatic Installation
1. Navigate to **Plugins → Add New** in your WordPress admin dashboard.
2. Search for "Fluent Cart FakerPress".
3. Click **Install Now**, then **Activate**.
4. Access the plugin via the new **FC FakerPress** menu item.

### Manual Installation
1. Download the plugin ZIP file.
2. Upload it to `/wp-content/plugins/fluent-cart-fakerpress/`.
3. Run `composer install` in the plugin directory.
4. Activate via the **Plugins** screen.
5. Access via the **FC FakerPress** menu.

### Development Setup
1. Clone the repository: `git clone https://github.com/mralaminahamed/fluent-cart-fakerpress.git`.
2. Install dependencies: `composer install && yarn install`.
3. Build assets: `yarn build`.
4. Activate the plugin.

**Requirements**:
- WordPress 5.0+
- PHP 7.4+ (8.0+ recommended)
- Fluent Cart plugin (latest version required)
- Minimum 256MB memory (512MB for large datasets)
- 100MB disk space for files and data

== Frequently Asked Questions ==

= How does Fluent Cart integration work? =
The plugin utilizes native Fluent Cart models for generation, ensuring data validation, relationship integrity, and compatibility with future updates. Direct database queries are avoided to maintain business logic enforcement.

= Can I generate data with complex relationships? =
Yes. Examples include linking orders to existing customers/products with inventory adjustments, modeling purchase history for loyalty progression, and validating coupon rules against categories and user data.

= How realistic is the generated data? =
Data is crafted using Faker for authentic details combined with e-commerce-specific logic, such as seasonal pricing, geographic accuracy, and customer lifecycle patterns.

= Is it safe for production use? =
**Caution**: Use exclusively in development or staging environments. Always back up your database prior to generation, start with small datasets, and avoid live sites without thorough testing.

= Can I customize generation? =
Yes, extensively! The plugin includes a comprehensive hook system with filters and actions for complete customization. Use data filters to modify data before creation, result filters for customization, and workflow actions for integration.

= What about performance for large datasets? =
Optimizations include batch processing, memory-efficient algorithms, and resumable progress tracking to handle extensive datasets without timeouts.

= How do I remove generated data? =
Employ WordPress deletion tools for items, bulk cleanup plugins, or targeted database queries (for advanced users). Back up data before any removal.

== Screenshots ==

1. Modern Admin Interface: React-based tabbed navigation with WordPress admin color integration.
   *(Screenshot: Admin dashboard overview)*
2. Product Generator: Controls for attributes, categories, and inventory.
   *(Screenshot: Product generation form)*
3. Customer Generator: Profile creation with demographics and loyalty tracking.
   *(Screenshot: Customer profile form)*

== Changelog ==

= 1.0.0 - November 11, 2025 =
* **Initial Release**: Smart generators for products and customers
* **Modern Interface**: React-based admin interface with WordPress admin color integration
* **REST API**: Complete REST API endpoints for programmatic access
* **Advanced Configuration**: Nested parameters and intelligent defaults
* **Code Quality**: PSR-4 compliant architecture with comprehensive validation
* **Extensibility**: Hook system for complete data customization and workflow integration

== Upgrade Notice ==

= 1.0.0 =
This is the initial release of Fluent Cart FakerPress. No upgrade path from previous versions.

== Other Notes ==

**Privacy & Data Handling**:
- Data is stored solely in your WordPress database; no external transmissions occur.
- Generated content is fictional and compliant with privacy standards.
- **Security Tip**: Restrict to non-production use and maintain regular backups.

**Contributing**:
- Repository: [GitHub](https://github.com/mralaminahamed/fluent-cart-fakerpress)
- Report issues or request features via GitHub Issues.
- Submit pull requests with tests, adhering to WordPress Coding Standards and PSR-4.

**Support**:
- Documentation: Included in the plugin and GitHub wiki.
- Forums: WordPress.org support threads.
- Professional assistance: Available for custom integrations.