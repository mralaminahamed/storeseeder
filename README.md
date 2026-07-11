# Fluent Cart FakerPress

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](http://www.gnu.org/licenses/gpl-2.0.txt)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)](https://php.net/)
[![Version](https://img.shields.io/badge/Version-2.0.0-green.svg)]()

🚀 **Generate realistic test data for your Fluent Cart store in seconds.** Products, customers, orders, refunds, coupons, attributes, logs, and more — through a modern React admin, schema-driven configuration, a full REST API, and optional AI/MCP integration.

## ❓ What is Fluent Cart FakerPress?

**Fluent Cart FakerPress** populates your Fluent Cart store with realistic test data. It's built for:

- 🧑‍💻 **Development** — feature work that needs data to work against
- 🧪 **Testing** — plugins, themes, and integrations
- 🎬 **Demos** — believable stores for clients and presentations
- 📈 **Performance** — realistic datasets at scale

All records are created through native Fluent Cart models, so they respect the same validation, relationships, and business logic as real data.

## 🛍️ The 13 Generators

| Generator | Creates |
| --- | --- |
| **Products** | Products with pricing, inventory, categories, content |
| **Product Variations** | Variation sets for variable products |
| **Customers** | Profiles, addresses, purchase history, preferences |
| **Orders** | Orders with line items, payment, shipping, tax, status |
| **Transactions** | Payment transactions tied to orders |
| **Refunds** | Full/partial refunds against existing successful charges |
| **Coupons** | Discount types, usage limits, validity, restrictions |
| **Shipping Plans** | Shipping methods and zones |
| **Tax Classes** | Tax classes and rates |
| **Attributes** | Attribute groups with taxonomy terms |
| **Cart Sessions** | Abandoned and active cart sessions |
| **Locations** | Geographic location data |
| **Logs** | Activity log entries across modules |

## 🚀 Get Started in Minutes

### For Users

1. **Install & Activate** the plugin.
2. Go to **WordPress Admin → FC FakerPress**.
3. Pick a generator, set a count, click **Generate**.

### For Developers

```bash
# Install dependencies
composer install && yarn install

# Build assets
yarn build

# Activate via WordPress Admin → Plugins → "Fluent Cart FakerPress"
```

## ✨ Key Features

- 🛍️ **13 smart generators** — see the table above
- 🎛️ **Schema-driven config** — each generator renders its own fields (ranges, toggles, nested options) with sensible defaults
- 🎨 **Modern admin** — React Router v7 SPA + Tailwind CSS v4 that adapts to your WordPress admin color scheme
- ⚡ **Fast** — batch generation through Fluent Cart models
- 🔌 **REST API** — `POST /wp-json/fluent-cart-fakerpress/v1/<resource>/generate`
- 🤖 **Optional MCP integration** — expose generators as AI tools via the WordPress Abilities API
- 🧩 **Extensible** — filters and actions across the generation lifecycle

## 📸 Screenshots

### Main Interface

![Fluent Cart FakerPress Admin Interface](.wordpress-org/screenshot-1.png)
_The React admin with generator grid and WordPress color-scheme integration._

### Generator Page

![Generator Page](.wordpress-org/screenshot-2.png)
_Schema-driven configuration with live preview._

### Customer Generator

![Customer Generator](.wordpress-org/screenshot-3.png)
_Customer generation with demographics and loyalty options._

## 📚 Documentation

| Document | Description |
| --- | --- |
| [📦 Installation Guide](docs/installation.md) | Setup instructions and requirements |
| [🚀 Usage Guide](docs/usage.md) | Using the generators and interface |
| [✨ Features Overview](docs/features.md) | Complete feature list |
| [🏗️ Architecture](docs/architecture.md) | Technical architecture and patterns |
| [🛠️ Development Guide](docs/development.md) | Contributing and development workflow |
| [📋 Changelog](docs/changelog.md) | Version history |

## 📋 Requirements

- **WordPress**: 5.0+
- **PHP**: 7.4+ (8.0+ recommended)
- **Fluent Cart**: active
- **Node.js**: 16+ and **Yarn** (development)
- **Composer**: for PHP dependencies
- **MCP (optional)**: WordPress Abilities API + `mcp-adapter` plugin

## 🛠️ Quick Commands

```bash
# Install
composer install && yarn install

# Development
yarn start               # Watch / rebuild on change
yarn build               # Production build

# Code quality
composer run lint        # PHP CodeSniffer (phpcs)
composer run analyse     # PHPStan static analysis
composer run test        # PHPUnit
```

## 🤖 Model Context Protocol (MCP) Integration

The plugin can expose every generator as an MCP tool so AI clients can generate test data conversationally. It requires:

- **WordPress Abilities API** — bundled in WordPress 6.9+, or installable separately
- **`mcp-adapter` plugin** — provides the MCP transport

MCP is entirely optional and degrades gracefully — the plugin works normally without these dependencies. When present, the abilities are served under the `fluent-cart-fakerpress` MCP server.

## 🏗️ Built for Reliability

- **Modern** — React 18 + WordPress REST API
- **WordPress-native** — integrates with the admin and with Fluent Cart's models
- **Quality-assured** — PSR-4, phpcs, PHPStan, PHPUnit smoke tests
- **Extensible** — documented hooks for full customization

## 🤝 Contributing

Contributions welcome. See the [Development Guide](docs/development.md) for setup, coding standards, and workflow.

## 📝 License

GPL v2 or later — see [LICENSE](LICENSE).

## 👨‍💻 Author

**Al Amin Ahamed**

- Website: [alaminahamed.com](https://alaminahamed.com)
- GitHub: [@mralaminahamed](https://github.com/mralaminahamed)
- Email: alamin.ahamed.dev@gmail.com

## 🆘 Support

[GitHub Issues](https://github.com/mralaminahamed/fluent-cart-fakerpress/issues) | [Changelog](docs/changelog.md)

---

**v2.0.0** | June 25, 2026
