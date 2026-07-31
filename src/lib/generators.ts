
import { __ } from "@wordpress/i18n";

import type { Generator } from "@/types";

export type { Generator };

/**
 * Every generator the admin offers, in the order the sidebar and the dashboard grid show
 * them (both sort on `order` within a category).
 *
 * Advanced is grouped by what a generator attaches to — products, then orders, then tax,
 * then shipping, then the standalone log — because that is how someone scans for one. The
 * grouping is also a valid generate-in-this-order sequence: Refunds follow Transactions
 * and Order Tax Lines follow Tax Classes, so working down the list top to bottom never
 * hits a missing prerequisite. Reordering has to preserve both, and the earlier order
 * preserved only the second: Shipping Plans sat at 2 with Shipping Classes at 9.
 */
export const generators: Generator[] = [
  {
    name: __("Products", "storeseeder"),
    category: "Core",
    order: 1,
    iconName: "box",
    description: __(
      "Create realistic products with prices, categories, inventory, and variations. Perfect for testing your store catalog and product pages.",
      "storeseeder",
    ),
    useCase: __("Store owners, theme developers, plugin testers", "storeseeder"),
    route: "products",
    resource: "product",
    popular: true,
    parameterConfig: {
      product_type: {
        description: __("Type of products to generate", "storeseeder"),
        type: "string",
        enum: ["physical", "digital", "mixed"],
        default: "mixed",
      },
      price_range: {
        description: __("Price range for generated products", "storeseeder"),
        type: "object",
        properties: {
          min: { type: "number", minimum: 0, default: 10 },
          max: { type: "number", minimum: 1, default: 500 },
        },
      },
      categories: {
        description: __("Product categories configuration", "storeseeder"),
        type: "object",
        properties: {
          create_new: {
            description: __("Create new categories if needed", "storeseeder"),
            type: "boolean",
            default: true,
          },
          max_per_product: {
            description: __("Maximum categories per product", "storeseeder"),
            type: "integer",
            minimum: 1,
            maximum: 10,
            default: 3,
          },
        },
      },
      attributes: {
        description: __("Product attributes configuration", "storeseeder"),
        type: "object",
        properties: {
          include_attributes: {
            description: __("Include product attributes", "storeseeder"),
            type: "boolean",
            default: true,
          },
          variation_count: {
            description: __("Number of variations for variable products", "storeseeder"),
            type: "integer",
            minimum: 1,
            maximum: 20,
            default: 5,
          },
        },
      },
      inventory: {
        description: __("Inventory settings for generated products", "storeseeder"),
        type: "object",
        properties: {
          manage_stock: {
            description: __("Enable stock management", "storeseeder"),
            type: "boolean",
            default: true,
          },
          stock_range: {
            description: __("Stock quantity range", "storeseeder"),
            type: "object",
            properties: {
              min: { type: "integer", minimum: 0, default: 0 },
              max: { type: "integer", minimum: 1, default: 100 },
            },
          },
        },
      },
      content_options: {
        description: __("Product content generation options", "storeseeder"),
        type: "object",
        properties: {
          description_length: {
            description: __("Length of product descriptions", "storeseeder"),
            type: "string",
            enum: ["short", "medium", "long"],
            default: "medium",
          },
          include_images: {
            description: __("Include placeholder images", "storeseeder"),
            type: "boolean",
            default: true,
          },
        },
      },
    },
  },
  {
    name: __("Customers", "storeseeder"),
    category: "Core",
    order: 2,
    iconName: "users",
    description: __(
      "Generate customer profiles with addresses, purchase history, and loyalty data. Essential for testing user accounts and customer management.",
      "storeseeder",
    ),
    useCase: __("Store owners, CRM developers, membership site testers", "storeseeder"),
    route: "customers",
    resource: "customer",
    popular: true,
    parameterConfig: {
      customer_types: {
        description: __("Types of customers to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["regular", "vip", "wholesale", "guest", "returning"] },
        default: ["regular", "returning"],
      },
      demographics: {
        description: __("Demographic distribution", "storeseeder"),
        type: "object",
        properties: {
          age_groups: {
            description: __("Age group distribution", "storeseeder"),
            type: "array",
            items: { type: "string", enum: ["18-25", "26-35", "36-45", "46-55", "56-65", "65+"] },
            default: ["26-35", "36-45", "46-55"],
          },
        },
      },
      address_preferences: {
        description: __("Address generation preferences", "storeseeder"),
        type: "object",
        properties: {
          include_billing: {
            description: __("Include billing addresses", "storeseeder"),
            type: "boolean",
            default: true,
          },
          include_shipping: {
            description: __("Include shipping addresses", "storeseeder"),
            type: "boolean",
            default: true,
          },
          different_addresses_ratio: {
            description: __("Percentage with different billing/shipping (0–100)", "storeseeder"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 30,
          },
        },
      },
      purchase_history: {
        description: __("Purchase history simulation", "storeseeder"),
        type: "object",
        properties: {
          simulate_history: {
            description: __("Generate purchase history metadata", "storeseeder"),
            type: "boolean",
            default: true,
          },
          loyalty_tiers: {
            description: __("Include loyalty tier assignments", "storeseeder"),
            type: "boolean",
            default: true,
          },
        },
      },
      contact_preferences: {
        description: __("Contact and communication preferences", "storeseeder"),
        type: "object",
        properties: {
          phone_numbers: {
            description: __("Include phone numbers", "storeseeder"),
            type: "boolean",
            default: true,
          },
          marketing_opt_in_ratio: {
            description: __("Percentage opted in for marketing (0–100)", "storeseeder"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 65,
          },
        },
      },
    },
  },
  {
    name: __("Orders", "storeseeder"),
    category: "Core",
    order: 3,
    iconName: "cart",
    description: __(
      "Create complete order histories with payments, shipping, and tax calculations. Test your checkout flow and order management system.",
      "storeseeder",
    ),
    useCase: __("Store owners, payment gateway developers, shipping testers", "storeseeder"),
    route: "orders",
    resource: "order",
    popular: true,
    parameterConfig: {
      order_status: {
        description: __("Order status distribution", "storeseeder"),
        type: "string",
        enum: ["pending", "processing", "completed", "cancelled", "on_hold", "refunded", "mixed"],
        default: "mixed",
      },
      customer_type: {
        description: __("Type of customers for orders", "storeseeder"),
        type: "string",
        enum: ["existing", "new", "mixed", "specific"],
        default: "mixed",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "storeseeder"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      customer_distribution: {
        description: __("Customer type distribution for mixed mode", "storeseeder"),
        type: "object",
        properties: {
          existing_ratio: {
            description: __("Percentage of existing customers (0–100)", "storeseeder"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 70,
          },
          new_ratio: {
            description: __("Percentage of new customers (0–100)", "storeseeder"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 30,
          },
        },
      },
      items_per_order: {
        description: __("Number of items per order", "storeseeder"),
        type: "object",
        properties: {
          min: { description: __("Minimum items", "storeseeder"), type: "integer", minimum: 1, default: 1 },
          max: { description: __("Maximum items", "storeseeder"), type: "integer", minimum: 1, maximum: 20, default: 5 },
        },
      },
      payment_methods: {
        description: __("Payment methods to use", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "bank_transfer", "cash_on_delivery", "credit_card"] },
        default: ["stripe", "paypal", "bank_transfer"],
      },
      geographical_distribution: {
        description: __("Geographic distribution of orders", "storeseeder"),
        type: "object",
        properties: {
          countries: {
            description: __("Countries to generate orders from", "storeseeder"),
            type: "array",
            items: { type: "string", enum: ["US", "CA", "GB", "AU", "DE", "FR"] },
            default: ["US", "CA", "GB"],
          },
        },
      },
    },
  },
  {
    name: __("Coupons", "storeseeder"),
    category: "Core",
    order: 4,
    iconName: "ticket",
    description: __(
      "Generate discount codes with various rules and restrictions. Perfect for testing promotional campaigns and discount logic.",
      "storeseeder",
    ),
    useCase: __("Store owners, marketing teams, discount plugin developers", "storeseeder"),
    route: "coupons",
    resource: "coupon",
    parameterConfig: {
      discount_types: {
        description: __("Types of discount coupons to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["percentage", "fixed", "free_shipping", "products"] },
        default: ["percentage", "fixed"],
      },
      discount_range: {
        description: __("Discount value range", "storeseeder"),
        type: "object",
        properties: {
          min_percentage: { type: "integer", minimum: 5, maximum: 95, default: 10 },
          max_percentage: { type: "integer", minimum: 5, maximum: 95, default: 50 },
          min_fixed: { type: "number", minimum: 1, default: 5 },
          max_fixed: { type: "number", minimum: 1, default: 100 },
        },
      },
      usage_limits: {
        description: __("Usage limitation settings", "storeseeder"),
        type: "object",
        properties: {
          set_usage_limits: { type: "boolean", default: true },
          max_uses: { type: "integer", minimum: 1, maximum: 1000, default: 100 },
          max_uses_per_user: { type: "integer", minimum: 1, maximum: 10, default: 1 },
        },
      },
      validity_period: {
        description: __("Coupon validity period configuration", "storeseeder"),
        type: "object",
        properties: {
          min_days: { type: "integer", minimum: 1, maximum: 365, default: 7 },
          max_days: { type: "integer", minimum: 1, maximum: 365, default: 90 },
        },
      },
      restrictions: {
        description: __("Coupon usage restrictions", "storeseeder"),
        type: "object",
        properties: {
          minimum_spend: { type: "boolean", default: true },
          maximum_spend: { type: "boolean", default: false },
          exclude_sale_items: { type: "boolean", default: false },
          product_restrictions: { type: "boolean", default: true },
        },
      },
    },
  },
  {
    name: __("Product Variations", "storeseeder"),
    category: "Advanced",
    order: 1,
    iconName: "branch",
    description: __(
      "Create complex product variations with size, color, and material options. Essential for testing variable product functionality.",
      "storeseeder",
    ),
    useCase: __("E-commerce developers, product catalog managers", "storeseeder"),
    route: "product-variations",
    resource: "product_variation",
    parameterConfig: {
      specific_product_id: {
        description: __("Specific product ID to generate variations for", "storeseeder"),
        type: "integer",
        minimum: 1,
      },
      product_types: {
        description: __("Product types to consider for variation generation", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["simple", "variable", "grouped", "external", "digital"] },
        default: ["simple", "variable"],
      },
      price_variance: {
        description: __("Price variance settings for variations", "storeseeder"),
        type: "object",
        properties: {
          min_percentage: { description: __("Minimum variance %", "storeseeder"), type: "number", minimum: -50, maximum: 50, default: -20 },
          max_percentage: { description: __("Maximum variance %", "storeseeder"), type: "number", minimum: -50, maximum: 100, default: 30 },
        },
      },
      stock_settings: {
        description: __("Stock management settings for variations", "storeseeder"),
        type: "object",
        properties: {
          manage_stock: { description: __("Enable stock management", "storeseeder"), type: "boolean", default: true },
          stock_range: {
            description: __("Stock quantity range", "storeseeder"),
            type: "object",
            properties: {
              min: { type: "integer", minimum: 0, default: 0 },
              max: { type: "integer", minimum: 1, default: 100 },
            },
          },
        },
      },
      variation_attributes: {
        description: __("Attribute generation settings", "storeseeder"),
        type: "object",
        properties: {
          create_missing_attributes: { description: __("Create missing attributes if needed", "storeseeder"), type: "boolean", default: true },
          max_attributes_per_variation: { description: __("Maximum attributes per variation", "storeseeder"), type: "integer", minimum: 1, maximum: 10, default: 3 },
        },
      },
    },
  },
  {
    name: __("Shipping Plans", "storeseeder"),
    category: "Advanced",
    order: 15,
    iconName: "truck",
    description: __(
      "Generate shipping methods, zones, and rate tables. Test delivery calculations and logistics workflows.",
      "storeseeder",
    ),
    useCase: __("Store owners, shipping plugin developers, logistics teams", "storeseeder"),
    route: "shipping-plans",
    resource: "shipping_plan",
    parameterConfig: {
      shipping_types: {
        description: __("Types of shipping methods to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["standard", "express", "overnight", "pickup", "free", "weight_based", "flat_rate"] },
        default: ["standard", "express", "free"],
      },
      cost_range: {
        description: __("Shipping cost range", "storeseeder"),
        type: "object",
        properties: {
          min: { description: __("Minimum cost", "storeseeder"), type: "number", minimum: 0, default: 0 },
          max: { description: __("Maximum cost", "storeseeder"), type: "number", minimum: 0, default: 50 },
        },
      },
      coverage_areas: {
        description: __("Geographic coverage areas", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["domestic", "international", "regional", "worldwide"] },
        default: ["domestic", "international"],
      },
      calculation_methods: {
        description: __("Shipping calculation methods", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["flat_rate", "weight_based", "price_based", "quantity_based"] },
        default: ["flat_rate", "weight_based"],
      },
      delivery_timeframes: {
        description: __("Delivery time ranges", "storeseeder"),
        type: "object",
        properties: {
          min_days: { description: __("Minimum delivery days", "storeseeder"), type: "integer", minimum: 0, default: 1 },
          max_days: { description: __("Maximum delivery days", "storeseeder"), type: "integer", minimum: 1, default: 14 },
        },
      },
    },
  },
  {
    name: __("Tax Classes", "storeseeder"),
    category: "Advanced",
    order: 13,
    iconName: "landmark",
    description: __(
      "Create tax rules and classes for different regions and product types. Perfect for testing international tax compliance.",
      "storeseeder",
    ),
    useCase: __("Store owners, accountants, tax plugin developers", "storeseeder"),
    route: "tax_classes",
    resource: "tax_class",
    parameterConfig: {
      tax_types: {
        description: __("Types of tax classes to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["standard", "reduced", "zero", "exempt", "digital"] },
        default: ["standard", "reduced", "zero"],
      },
      jurisdictions: {
        description: __("Tax jurisdictions to generate rates for", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["country", "state", "city", "county", "postcode"] },
        default: ["country", "state"],
      },
      rate_ranges: {
        description: __("Tax rate ranges by type", "storeseeder"),
        type: "object",
        properties: {
          standard: {
            description: __("Standard tax rate range", "storeseeder"),
            type: "object",
            properties: {
              min: { type: "number", minimum: 0, maximum: 50, default: 5 },
              max: { type: "number", minimum: 0, maximum: 50, default: 25 },
            },
          },
          reduced: {
            description: __("Reduced tax rate range", "storeseeder"),
            type: "object",
            properties: {
              min: { type: "number", minimum: 0, maximum: 20, default: 1 },
              max: { type: "number", minimum: 0, maximum: 20, default: 10 },
            },
          },
        },
      },
      location_coverage: {
        description: __("Geographic coverage for tax rates", "storeseeder"),
        type: "object",
        properties: {
          countries: {
            description: __("Countries to generate tax rates for", "storeseeder"),
            type: "array",
            items: { type: "string" },
            default: ["US", "CA", "GB", "AU", "DE"],
          },
          include_compound: {
            description: __("Include compound tax rates", "storeseeder"),
            type: "boolean",
            default: true,
          },
        },
      },
    },
  },
  {
    name: __("Transactions", "storeseeder"),
    category: "Advanced",
    order: 8,
    iconName: "card",
    description: __(
      "Generate payment transaction records with multiple gateways and statuses. Test financial reporting and reconciliation.",
      "storeseeder",
    ),
    useCase: __("Payment gateway developers, accountants, financial analysts", "storeseeder"),
    route: "transactions",
    resource: "transaction",
    parameterConfig: {
      customer_type: {
        description: __("Type of customers for transactions", "storeseeder"),
        type: "string",
        enum: ["all", "specific", "existing_customers_only", "new_customers_only"],
        default: "all",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "storeseeder"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      order_status_filter: {
        description: __("Filter orders by status", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["pending", "processing", "completed", "cancelled", "on_hold", "refunded"] },
        default: ["pending", "processing", "completed"],
      },
      transaction_types: {
        description: __("Types of transactions to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["payment", "refund", "adjustment", "fee", "commission"] },
        default: ["payment", "refund"],
      },
      payment_gateways: {
        description: __("Payment gateways to use for transactions", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "square", "authorize_net", "braintree", "razorpay", "mollie"] },
        default: ["stripe", "paypal", "square"],
      },
      amount_range: {
        description: __("Transaction amount range", "storeseeder"),
        type: "object",
        properties: {
          min: { type: "number", minimum: 0, default: 1 },
          max: { type: "number", minimum: 1, default: 1000 },
        },
      },
      status_distribution: {
        description: __("Transaction status distribution", "storeseeder"),
        type: "object",
        properties: {
          success_rate: { type: "integer", minimum: 0, maximum: 100, default: 85 },
          pending_rate: { type: "integer", minimum: 0, maximum: 100, default: 10 },
          failed_rate: { type: "integer", minimum: 0, maximum: 100, default: 5 },
        },
      },
    },
  },
  {
    name: __("Cart Sessions", "storeseeder"),
    category: "Advanced",
    order: 7,
    iconName: "bag",
    description: __(
      "Create shopping cart abandonment scenarios and session data. Test cart recovery systems and analytics.",
      "storeseeder",
    ),
    useCase: __("Marketing teams, cart recovery plugin developers", "storeseeder"),
    route: "cart-sessions",
    resource: "cart_session",
    parameterConfig: {
      customer_type: {
        description: __("Type of customers for cart sessions", "storeseeder"),
        type: "string",
        enum: ["existing", "new", "mixed", "specific", "guest_only"],
        default: "mixed",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "storeseeder"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      guest_cart_ratio: {
        description: __("Percentage of guest carts (0–100)", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 40,
      },
      abandonment_rate: {
        description: __("Cart abandonment rate percentage (0–100)", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 30,
      },
      cart_value_range: {
        description: __("Cart value range", "storeseeder"),
        type: "object",
        properties: {
          min: { description: __("Minimum cart value", "storeseeder"), type: "number", minimum: 0, default: 5 },
          max: { description: __("Maximum cart value", "storeseeder"), type: "number", minimum: 1, default: 500 },
        },
      },
      items_per_cart: {
        description: __("Number of items per cart session", "storeseeder"),
        type: "object",
        properties: {
          min: { description: __("Minimum items per cart", "storeseeder"), type: "integer", minimum: 1, default: 1 },
          max: { description: __("Maximum items per cart", "storeseeder"), type: "integer", minimum: 1, maximum: 15, default: 5 },
        },
      },
      abandonment_tracking: {
        description: __("Abandonment tracking settings", "storeseeder"),
        type: "object",
        properties: {
          generate_reminders: { description: __("Generate abandoned cart reminders", "storeseeder"), type: "boolean", default: true },
          reminder_count: { description: __("Maximum number of reminders", "storeseeder"), type: "integer", minimum: 0, maximum: 10, default: 3 },
          recovery_rate: { description: __("Cart recovery rate percentage (0–100)", "storeseeder"), type: "integer", minimum: 0, maximum: 100, default: 15 },
        },
      },
    },
  },
  {
    name: __("Product Attributes", "storeseeder"),
    category: "Advanced",
    order: 2,
    iconName: "listtree",
    description: __(
      "Generate product attributes such as Text, Color, and Image types. Attributes can be used to define product variations and filtering options.",
      "storeseeder",
    ),
    useCase: __("E-commerce developers, product catalog managers", "storeseeder"),
    route: "attributes",
    resource: "attribute",
    parameterConfig: {
      attribute_types: {
        description: __("Types of attributes to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["Text", "Color", "Image"] },
        default: ["Text", "Color"],
      },
    },
  },
  {
    name: __("Product Categories", "storeseeder"),
    category: "Advanced",
    order: 3,
    iconName: "folder",
    description: __(
      "Generate product categories, nested where asked, and file existing products under them. For testing category archives, breadcrumbs, and filtered queries.",
      "storeseeder",
    ),
    useCase: __("Theme developers, store owners organising a catalogue", "storeseeder"),
    route: "product_categories",
    resource: "product_category",
    parameterConfig: {
      products_per_category: {
        description: __("How many products to file under each category", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 30,
        default: 5,
      },
      nested_ratio: {
        description: __("Percentage created beneath an existing category (0–100)", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 35,
      },
    },
  },
  {
    name: __("Product Brands", "storeseeder"),
    category: "Advanced",
    order: 4,
    iconName: "tag",
    description: __(
      "Generate product brands and attach them to existing products, for testing brand archives, filters, and product pages. Needs a platform that has brands, and products to attach them to.",
      "storeseeder",
    ),
    useCase: __("Store owners with multi-brand catalogues, theme developers", "storeseeder"),
    route: "brands",
    resource: "brand",
    parameterConfig: {
      products_per_brand: {
        description: __("How many products to attach each brand to", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 20,
        default: 3,
      },
      nested_ratio: {
        description: __("Percentage created as sub-brands of an existing brand (0–100)", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 25,
      },
    },
  },
  {
    name: __("Product Tags", "storeseeder"),
    category: "Advanced",
    order: 5,
    iconName: "tags",
    description: __(
      "Generate product tags and apply them to existing products, for testing tag archives, related products, and faceted search. WooCommerce has tags; Fluent Cart does not.",
      "storeseeder",
    ),
    useCase: __("Theme developers, merchandisers testing faceted search", "storeseeder"),
    route: "product_tags",
    resource: "product_tag",
    parameterConfig: {
      products_per_tag: {
        description: __("How many products to apply each tag to", "storeseeder"),
        type: "integer",
        minimum: 0,
        maximum: 50,
        default: 6,
      },
    },
  },
  {
    name: __("Refunds", "storeseeder"),
    category: "Advanced",
    order: 9,
    iconName: "coins",
    description: __(
      "Generate refund records against existing orders. Requires completed or processing orders. Returns refund IDs, amounts, statuses, and transaction IDs.",
      "storeseeder",
    ),
    useCase: __("Store owners testing refund workflows, payment gateway developers", "storeseeder"),
    route: "refunds",
    resource: "refund",
    parameterConfig: {
      order_statuses: {
        description: __("Order statuses eligible for refund generation", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["completed", "processing", "pending", "cancelled"] },
        default: ["completed", "processing"],
      },
      payment_gateways: {
        description: __("Payment gateways for transaction IDs", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "square", "bank_transfer", "authorize_net"] },
        default: ["stripe", "paypal", "square"],
      },
    },
  },
  {
    name: __("Logs", "storeseeder"),
    category: "Advanced",
    order: 17,
    iconName: "scroll",
    description: __(
      "Generate activity log entries for orders, products, customers, and system events. Useful for testing log views and audit trails.",
      "storeseeder",
    ),
    useCase: __("Developers testing audit logs, admin panel log views", "storeseeder"),
    route: "logs",
    resource: "log",
    parameterConfig: {
      log_types: {
        description: __("Log severity types to generate", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["info", "warning", "error", "success"] },
        default: ["info", "warning", "error", "success"],
      },
      objects: {
        description: __("Object types to generate log entries for", "storeseeder"),
        type: "array",
        items: { type: "string", enum: ["order", "product", "customer", "coupon", "refund", "cart", "transaction", "system"] },
        default: ["order", "product", "customer", "coupon", "refund", "cart", "transaction", "system"],
      },
    },
  },
  {
    name: __("Shipping Classes", "storeseeder"),
    category: "Advanced",
    order: 16,
    iconName: "boxes",
    description: __(
      "Generate shipping classes that group products with similar shipping requirements, each with a cost and per-item flag.",
      "storeseeder",
    ),
    useCase: __("Store owners configuring shipping rate groups", "storeseeder"),
    route: "shipping_classes",
    resource: "shipping_class",
    parameterConfig: {},
  },
  {
    name: __("Labels", "storeseeder"),
    category: "Advanced",
    order: 12,
    iconName: "tags",
    description: __(
      "Generate labels (tags) and attach them to existing orders and customers. Requires existing orders or customers to attach to.",
      "storeseeder",
    ),
    useCase: __("Teams segmenting orders and customers with tags", "storeseeder"),
    route: "labels",
    resource: "label",
    parameterConfig: {},
  },
  {
    name: __("Order Tax Lines", "storeseeder"),
    category: "Advanced",
    order: 14,
    iconName: "percent",
    description: __(
      "Generate per-order tax lines linking orders to tax rates with the tax collected. Requires existing orders and tax rates.",
      "storeseeder",
    ),
    useCase: __("Developers testing tax reports and collected-tax views", "storeseeder"),
    route: "order_tax_rates",
    resource: "order_tax_rate",
    parameterConfig: {},
  },
  {
    name: __("Product Downloads", "storeseeder"),
    category: "Advanced",
    order: 6,
    iconName: "download",
    description: __(
      "Generate downloadable files for products and grant download permissions on existing orders. Requires existing products.",
      "storeseeder",
    ),
    useCase: __("Developers testing digital-product fulfillment", "storeseeder"),
    route: "product_downloads",
    resource: "product_download",
    parameterConfig: {},
  },
  {
    name: __("Subscriptions", "storeseeder"),
    category: "Advanced",
    order: 10,
    iconName: "repeat",
    description: __(
      "Generate subscription records against existing orders. Records seed as fixtures; charging them is your platform's job and may need a paid add-on. Requires existing orders and products.",
      "storeseeder",
    ),
    useCase: __("Developers testing recurring-billing views and reports", "storeseeder"),
    route: "subscriptions",
    resource: "subscription",
    parameterConfig: {},
  },
  {
    name: __("Licenses", "storeseeder"),
    category: "Advanced",
    order: 11,
    iconName: "key",
    description: __(
      "Create software licences against existing orders — keys, site limits, activation counts and expiry dates, including licences already at their limit and some long expired. Requires Fluent Cart Pro, which owns the licensing tables.",
      "storeseeder",
    ),
    useCase: __(
      "Developers testing licence validation, activation limits and renewal notices",
      "storeseeder",
    ),
    route: "licenses",
    resource: "license",
    parameterConfig: {},
  },
];

// ---------------------------------------------------------------------------
// Ordering and labels
// ---------------------------------------------------------------------------

/**
 * Categories, in the order every surface shows them.
 *
 * These are stable keys, never translated. `category` used to hold a translated string
 * while the sidebar filtered on the literal `"Core"`, so on a translated site the
 * comparison failed and the sidebar groups came out empty.
 */
/**
 * The display name for a canonical resource name.
 *
 * The server speaks in resources (`cart_session`), the admin in names ("Cart Sessions"), and
 * this is the one place that maps between them. Falls back to the raw key rather than to
 * nothing, so a resource added by a third-party platform still reads as something.
 */
export function resourceLabel(resource: string): string {
  return generators.find((g) => g.resource === resource)?.name ?? resource;
}

export const CATEGORY_ORDER = ["Core", "Advanced", "Enhanced"] as const;

export type Category = (typeof CATEGORY_ORDER)[number];

/** The translated name of a category. Unknown keys pass through unchanged. */
export function categoryLabel(category: string): string {
  switch (category) {
    case "Core":
      return __("Core", "storeseeder");
    case "Advanced":
      return __("Advanced", "storeseeder");
    case "Enhanced":
      return __("Enhanced", "storeseeder");
    default:
      return category;
  }
}

/** Categories that actually have generators, in CATEGORY_ORDER. */
export function usedCategories(): string[] {
  return CATEGORY_ORDER.filter((category) =>
    generators.some((g) => g.category === category),
  );
}

/**
 * Every generator in display order: by category, then by `order` within it.
 *
 * The command palette listed them in declaration order instead, so reordering the
 * sidebar left the palette showing the previous sequence — the two disagreed about what
 * comes after Product Variations.
 */
export function sortedGenerators(): Generator[] {
  const rank = (category: string) => {
    const i = CATEGORY_ORDER.indexOf(category as Category);
    // An unknown category sorts last rather than first, which is what -1 would do.
    return -1 === i ? CATEGORY_ORDER.length : i;
  };

  return [...generators].sort(
    (a, b) => rank(a.category) - rank(b.category) || a.order - b.order,
  );
}

/** Generators grouped for display: categories in order, each with its own sorted list. */
export function generatorsByCategory(): Array<{
  category: string;
  label: string;
  items: Generator[];
}> {
  return usedCategories().map((category) => ({
    category,
    label: categoryLabel(category),
    items: sortedGenerators().filter((g) => g.category === category),
  }));
}
