import {
  type LucideIcon,
  Package,
  Users,
  ShoppingCart,
  Tag,
  Settings,
  Truck,
  DollarSign,
  CreditCard,
  ShoppingBag,
  Layers,
  ReceiptText,
  ScrollText,
  Boxes,
  Tags,
  Percent,
  Download,
  Repeat,
} from "lucide-react";

import { __ } from "@wordpress/i18n";

import type { Generator } from "@/admin/types";

export type { Generator };

export const generators: Generator[] = [
  {
    name: __("Products", "fluent-cart-fakerpress"),
    category: __("Core", "fluent-cart-fakerpress"),
    order: 1,
    icon: Package,
    iconName: "box",
    description: __(
      "Create realistic products with prices, categories, inventory, and variations. Perfect for testing your store catalog and product pages.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, theme developers, plugin testers", "fluent-cart-fakerpress"),
    route: "products",
    popular: true,
    parameterConfig: {
      product_type: {
        description: __("Type of products to generate", "fluent-cart-fakerpress"),
        type: "string",
        enum: ["physical", "digital", "mixed"],
        default: "mixed",
      },
      price_range: {
        description: __("Price range for generated products", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { type: "number", minimum: 0, default: 10 },
          max: { type: "number", minimum: 1, default: 500 },
        },
      },
      categories: {
        description: __("Product categories configuration", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          create_new: {
            description: __("Create new categories if needed", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          max_per_product: {
            description: __("Maximum categories per product", "fluent-cart-fakerpress"),
            type: "integer",
            minimum: 1,
            maximum: 10,
            default: 3,
          },
        },
      },
      attributes: {
        description: __("Product attributes configuration", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          include_attributes: {
            description: __("Include product attributes", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          variation_count: {
            description: __("Number of variations for variable products", "fluent-cart-fakerpress"),
            type: "integer",
            minimum: 1,
            maximum: 20,
            default: 5,
          },
        },
      },
      inventory: {
        description: __("Inventory settings for generated products", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          manage_stock: {
            description: __("Enable stock management", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          stock_range: {
            description: __("Stock quantity range", "fluent-cart-fakerpress"),
            type: "object",
            properties: {
              min: { type: "integer", minimum: 0, default: 0 },
              max: { type: "integer", minimum: 1, default: 100 },
            },
          },
        },
      },
      content_options: {
        description: __("Product content generation options", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          description_length: {
            description: __("Length of product descriptions", "fluent-cart-fakerpress"),
            type: "string",
            enum: ["short", "medium", "long"],
            default: "medium",
          },
          include_images: {
            description: __("Include placeholder images", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
        },
      },
    },
  },
  {
    name: __("Customers", "fluent-cart-fakerpress"),
    category: __("Core", "fluent-cart-fakerpress"),
    order: 2,
    icon: Users,
    iconName: "users",
    description: __(
      "Generate customer profiles with addresses, purchase history, and loyalty data. Essential for testing user accounts and customer management.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, CRM developers, membership site testers", "fluent-cart-fakerpress"),
    route: "customers",
    popular: true,
    parameterConfig: {
      customer_types: {
        description: __("Types of customers to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["regular", "vip", "wholesale", "guest", "returning"] },
        default: ["regular", "returning"],
      },
      demographics: {
        description: __("Demographic distribution", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          age_groups: {
            description: __("Age group distribution", "fluent-cart-fakerpress"),
            type: "array",
            items: { type: "string", enum: ["18-25", "26-35", "36-45", "46-55", "56-65", "65+"] },
            default: ["26-35", "36-45", "46-55"],
          },
        },
      },
      address_preferences: {
        description: __("Address generation preferences", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          include_billing: {
            description: __("Include billing addresses", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          include_shipping: {
            description: __("Include shipping addresses", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          different_addresses_ratio: {
            description: __("Percentage with different billing/shipping (0-100)", "fluent-cart-fakerpress"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 30,
          },
        },
      },
      purchase_history: {
        description: __("Purchase history simulation", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          simulate_history: {
            description: __("Generate purchase history metadata", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          loyalty_tiers: {
            description: __("Include loyalty tier assignments", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
        },
      },
      contact_preferences: {
        description: __("Contact and communication preferences", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          phone_numbers: {
            description: __("Include phone numbers", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
          marketing_opt_in_ratio: {
            description: __("Percentage opted in for marketing (0-100)", "fluent-cart-fakerpress"),
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
    name: __("Orders", "fluent-cart-fakerpress"),
    category: __("Core", "fluent-cart-fakerpress"),
    order: 3,
    icon: ShoppingCart,
    iconName: "cart",
    description: __(
      "Create complete order histories with payments, shipping, and tax calculations. Test your checkout flow and order management system.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, payment gateway developers, shipping testers", "fluent-cart-fakerpress"),
    route: "orders",
    popular: true,
    parameterConfig: {
      order_status: {
        description: __("Order status distribution", "fluent-cart-fakerpress"),
        type: "string",
        enum: ["pending", "processing", "completed", "cancelled", "on_hold", "refunded", "mixed"],
        default: "mixed",
      },
      customer_type: {
        description: __("Type of customers for orders", "fluent-cart-fakerpress"),
        type: "string",
        enum: ["existing", "new", "mixed", "specific"],
        default: "mixed",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      customer_distribution: {
        description: __("Customer type distribution for mixed mode", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          existing_ratio: {
            description: __("Percentage of existing customers (0-100)", "fluent-cart-fakerpress"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 70,
          },
          new_ratio: {
            description: __("Percentage of new customers (0-100)", "fluent-cart-fakerpress"),
            type: "integer",
            minimum: 0,
            maximum: 100,
            default: 30,
          },
        },
      },
      items_per_order: {
        description: __("Number of items per order", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { description: __("Minimum items", "fluent-cart-fakerpress"), type: "integer", minimum: 1, default: 1 },
          max: { description: __("Maximum items", "fluent-cart-fakerpress"), type: "integer", minimum: 1, maximum: 20, default: 5 },
        },
      },
      payment_methods: {
        description: __("Payment methods to use", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "bank_transfer", "cash_on_delivery", "credit_card"] },
        default: ["stripe", "paypal", "bank_transfer"],
      },
      geographical_distribution: {
        description: __("Geographic distribution of orders", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          countries: {
            description: __("Countries to generate orders from", "fluent-cart-fakerpress"),
            type: "array",
            items: { type: "string", enum: ["US", "CA", "GB", "AU", "DE", "FR"] },
            default: ["US", "CA", "GB"],
          },
        },
      },
    },
  },
  {
    name: __("Coupons", "fluent-cart-fakerpress"),
    category: __("Core", "fluent-cart-fakerpress"),
    order: 4,
    icon: Tag,
    iconName: "tag",
    description: __(
      "Generate discount codes with various rules and restrictions. Perfect for testing promotional campaigns and discount logic.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, marketing teams, discount plugin developers", "fluent-cart-fakerpress"),
    route: "coupons",
    parameterConfig: {
      discount_types: {
        description: __("Types of discount coupons to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["percentage", "fixed", "free_shipping", "products"] },
        default: ["percentage", "fixed"],
      },
      discount_range: {
        description: __("Discount value range", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min_percentage: { type: "integer", minimum: 5, maximum: 95, default: 10 },
          max_percentage: { type: "integer", minimum: 5, maximum: 95, default: 50 },
          min_fixed: { type: "number", minimum: 1, default: 5 },
          max_fixed: { type: "number", minimum: 1, default: 100 },
        },
      },
      usage_limits: {
        description: __("Usage limitation settings", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          set_usage_limits: { type: "boolean", default: true },
          max_uses: { type: "integer", minimum: 1, maximum: 1000, default: 100 },
          max_uses_per_user: { type: "integer", minimum: 1, maximum: 10, default: 1 },
        },
      },
      validity_period: {
        description: __("Coupon validity period configuration", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min_days: { type: "integer", minimum: 1, maximum: 365, default: 7 },
          max_days: { type: "integer", minimum: 1, maximum: 365, default: 90 },
        },
      },
      restrictions: {
        description: __("Coupon usage restrictions", "fluent-cart-fakerpress"),
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
    name: __("Product Variations", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 1,
    icon: Settings,
    iconName: "sliders",
    description: __(
      "Create complex product variations with size, color, and material options. Essential for testing variable product functionality.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("E-commerce developers, product catalog managers", "fluent-cart-fakerpress"),
    route: "product-variations",
    parameterConfig: {
      specific_product_id: {
        description: __("Specific product ID to generate variations for", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 1,
      },
      product_types: {
        description: __("Product types to consider for variation generation", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["simple", "variable", "grouped", "external", "digital"] },
        default: ["simple", "variable"],
      },
      price_variance: {
        description: __("Price variance settings for variations", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min_percentage: { description: __("Minimum variance %", "fluent-cart-fakerpress"), type: "number", minimum: -50, maximum: 50, default: -20 },
          max_percentage: { description: __("Maximum variance %", "fluent-cart-fakerpress"), type: "number", minimum: -50, maximum: 100, default: 30 },
        },
      },
      stock_settings: {
        description: __("Stock management settings for variations", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          manage_stock: { description: __("Enable stock management", "fluent-cart-fakerpress"), type: "boolean", default: true },
          stock_range: {
            description: __("Stock quantity range", "fluent-cart-fakerpress"),
            type: "object",
            properties: {
              min: { type: "integer", minimum: 0, default: 0 },
              max: { type: "integer", minimum: 1, default: 100 },
            },
          },
        },
      },
      variation_attributes: {
        description: __("Attribute generation settings", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          create_missing_attributes: { description: __("Create missing attributes if needed", "fluent-cart-fakerpress"), type: "boolean", default: true },
          max_attributes_per_variation: { description: __("Maximum attributes per variation", "fluent-cart-fakerpress"), type: "integer", minimum: 1, maximum: 10, default: 3 },
        },
      },
    },
  },
  {
    name: __("Shipping Plans", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 2,
    icon: Truck,
    iconName: "truck",
    description: __(
      "Generate shipping methods, zones, and rate tables. Test delivery calculations and logistics workflows.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, shipping plugin developers, logistics teams", "fluent-cart-fakerpress"),
    route: "shipping-plans",
    parameterConfig: {
      shipping_types: {
        description: __("Types of shipping methods to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["standard", "express", "overnight", "pickup", "free", "weight_based", "flat_rate"] },
        default: ["standard", "express", "free"],
      },
      cost_range: {
        description: __("Shipping cost range", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { description: __("Minimum cost", "fluent-cart-fakerpress"), type: "number", minimum: 0, default: 0 },
          max: { description: __("Maximum cost", "fluent-cart-fakerpress"), type: "number", minimum: 0, default: 50 },
        },
      },
      coverage_areas: {
        description: __("Geographic coverage areas", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["domestic", "international", "regional", "worldwide"] },
        default: ["domestic", "international"],
      },
      calculation_methods: {
        description: __("Shipping calculation methods", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["flat_rate", "weight_based", "price_based", "quantity_based"] },
        default: ["flat_rate", "weight_based"],
      },
      delivery_timeframes: {
        description: __("Delivery time ranges", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min_days: { description: __("Minimum delivery days", "fluent-cart-fakerpress"), type: "integer", minimum: 0, default: 1 },
          max_days: { description: __("Maximum delivery days", "fluent-cart-fakerpress"), type: "integer", minimum: 1, default: 14 },
        },
      },
    },
  },
  {
    name: __("Tax Classes", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 3,
    icon: DollarSign,
    iconName: "dollar",
    description: __(
      "Create tax rules and classes for different regions and product types. Perfect for testing international tax compliance.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners, accountants, tax plugin developers", "fluent-cart-fakerpress"),
    route: "tax_classes",
    parameterConfig: {
      tax_types: {
        description: __("Types of tax classes to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["standard", "reduced", "zero", "exempt", "digital"] },
        default: ["standard", "reduced", "zero"],
      },
      jurisdictions: {
        description: __("Tax jurisdictions to generate rates for", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["country", "state", "city", "county", "postcode"] },
        default: ["country", "state"],
      },
      rate_ranges: {
        description: __("Tax rate ranges by type", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          standard: {
            description: __("Standard tax rate range", "fluent-cart-fakerpress"),
            type: "object",
            properties: {
              min: { type: "number", minimum: 0, maximum: 50, default: 5 },
              max: { type: "number", minimum: 0, maximum: 50, default: 25 },
            },
          },
          reduced: {
            description: __("Reduced tax rate range", "fluent-cart-fakerpress"),
            type: "object",
            properties: {
              min: { type: "number", minimum: 0, maximum: 20, default: 1 },
              max: { type: "number", minimum: 0, maximum: 20, default: 10 },
            },
          },
        },
      },
      location_coverage: {
        description: __("Geographic coverage for tax rates", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          countries: {
            description: __("Countries to generate tax rates for", "fluent-cart-fakerpress"),
            type: "array",
            items: { type: "string" },
            default: ["US", "CA", "GB", "AU", "DE"],
          },
          include_compound: {
            description: __("Include compound tax rates", "fluent-cart-fakerpress"),
            type: "boolean",
            default: true,
          },
        },
      },
    },
  },
  {
    name: __("Transactions", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 4,
    icon: CreditCard,
    iconName: "card",
    description: __(
      "Generate payment transaction records with multiple gateways and statuses. Test financial reporting and reconciliation.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Payment gateway developers, accountants, financial analysts", "fluent-cart-fakerpress"),
    route: "transactions",
    parameterConfig: {
      customer_type: {
        description: __("Type of customers for transactions", "fluent-cart-fakerpress"),
        type: "string",
        enum: ["all", "specific", "existing_customers_only", "new_customers_only"],
        default: "all",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      order_status_filter: {
        description: __("Filter orders by status", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["pending", "processing", "completed", "cancelled", "on_hold", "refunded"] },
        default: ["pending", "processing", "completed"],
      },
      transaction_types: {
        description: __("Types of transactions to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["payment", "refund", "adjustment", "fee", "commission"] },
        default: ["payment", "refund"],
      },
      payment_gateways: {
        description: __("Payment gateways to use for transactions", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "square", "authorize_net", "braintree", "razorpay", "mollie"] },
        default: ["stripe", "paypal", "square"],
      },
      amount_range: {
        description: __("Transaction amount range", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { type: "number", minimum: 0, default: 1 },
          max: { type: "number", minimum: 1, default: 1000 },
        },
      },
      status_distribution: {
        description: __("Transaction status distribution", "fluent-cart-fakerpress"),
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
    name: __("Cart Sessions", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 5,
    icon: ShoppingBag,
    iconName: "bag",
    description: __(
      "Create shopping cart abandonment scenarios and session data. Test cart recovery systems and analytics.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Marketing teams, cart recovery plugin developers", "fluent-cart-fakerpress"),
    route: "cart-sessions",
    parameterConfig: {
      customer_type: {
        description: __("Type of customers for cart sessions", "fluent-cart-fakerpress"),
        type: "string",
        enum: ["existing", "new", "mixed", "specific", "guest_only"],
        default: "mixed",
      },
      specific_customer_id: {
        description: __("Specific customer ID (when customer_type is 'specific')", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 1,
        dependsOn: { customer_type: "specific" },
      },
      guest_cart_ratio: {
        description: __("Percentage of guest carts (0-100)", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 40,
      },
      abandonment_rate: {
        description: __("Cart abandonment rate percentage (0-100)", "fluent-cart-fakerpress"),
        type: "integer",
        minimum: 0,
        maximum: 100,
        default: 30,
      },
      cart_value_range: {
        description: __("Cart value range", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { description: __("Minimum cart value", "fluent-cart-fakerpress"), type: "number", minimum: 0, default: 5 },
          max: { description: __("Maximum cart value", "fluent-cart-fakerpress"), type: "number", minimum: 1, default: 500 },
        },
      },
      items_per_cart: {
        description: __("Number of items per cart session", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          min: { description: __("Minimum items per cart", "fluent-cart-fakerpress"), type: "integer", minimum: 1, default: 1 },
          max: { description: __("Maximum items per cart", "fluent-cart-fakerpress"), type: "integer", minimum: 1, maximum: 15, default: 5 },
        },
      },
      abandonment_tracking: {
        description: __("Abandonment tracking settings", "fluent-cart-fakerpress"),
        type: "object",
        properties: {
          generate_reminders: { description: __("Generate abandoned cart reminders", "fluent-cart-fakerpress"), type: "boolean", default: true },
          reminder_count: { description: __("Maximum number of reminders", "fluent-cart-fakerpress"), type: "integer", minimum: 0, maximum: 10, default: 3 },
          recovery_rate: { description: __("Cart recovery rate percentage (0-100)", "fluent-cart-fakerpress"), type: "integer", minimum: 0, maximum: 100, default: 15 },
        },
      },
    },
  },
  {
    name: __("Attributes", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 6,
    icon: Layers,
    iconName: "layers",
    description: __(
      "Generate product attributes such as Text, Color, and Image types. Attributes can be used to define product variations and filtering options.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("E-commerce developers, product catalog managers", "fluent-cart-fakerpress"),
    route: "attributes",
    parameterConfig: {
      attribute_types: {
        description: __("Types of attributes to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["Text", "Color", "Image"] },
        default: ["Text", "Color"],
      },
    },
  },
  {
    name: __("Refunds", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 7,
    icon: ReceiptText,
    iconName: "receipt",
    description: __(
      "Generate refund records against existing orders. Requires completed or processing orders. Returns refund IDs, amounts, statuses, and transaction IDs.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners testing refund workflows, payment gateway developers", "fluent-cart-fakerpress"),
    route: "refunds",
    parameterConfig: {
      order_statuses: {
        description: __("Order statuses eligible for refund generation", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["completed", "processing", "pending", "cancelled"] },
        default: ["completed", "processing"],
      },
      payment_gateways: {
        description: __("Payment gateways for transaction IDs", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal", "square", "bank_transfer", "authorize_net"] },
        default: ["stripe", "paypal", "square"],
      },
    },
  },
  {
    name: __("Logs", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 8,
    icon: ScrollText,
    iconName: "scroll",
    description: __(
      "Generate activity log entries for orders, products, customers, and system events. Useful for testing log views and audit trails.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Developers testing audit logs, admin panel log views", "fluent-cart-fakerpress"),
    route: "logs",
    parameterConfig: {
      log_types: {
        description: __("Log severity types to generate", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["info", "warning", "error", "success"] },
        default: ["info", "warning", "error", "success"],
      },
      objects: {
        description: __("Object types to generate log entries for", "fluent-cart-fakerpress"),
        type: "array",
        items: { type: "string", enum: ["order", "product", "customer", "coupon", "refund", "cart", "transaction", "system"] },
        default: ["order", "product", "customer", "coupon", "refund", "cart", "transaction", "system"],
      },
    },
  },
  {
    name: __("Shipping Classes", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 9,
    icon: Boxes,
    iconName: "boxes",
    description: __(
      "Generate shipping classes that group products with similar shipping requirements, each with a cost and per-item flag.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Store owners configuring shipping rate groups", "fluent-cart-fakerpress"),
    route: "shipping_classes",
    parameterConfig: {},
  },
  {
    name: __("Labels", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 10,
    icon: Tags,
    iconName: "tags",
    description: __(
      "Generate labels (tags) and attach them to existing orders and customers. Requires existing orders or customers to attach to.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Teams segmenting orders and customers with tags", "fluent-cart-fakerpress"),
    route: "labels",
    parameterConfig: {},
  },
  {
    name: __("Order Tax Lines", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 11,
    icon: Percent,
    iconName: "percent",
    description: __(
      "Generate per-order tax lines linking orders to tax rates with the tax collected. Requires existing orders and tax rates.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Developers testing tax reports and collected-tax views", "fluent-cart-fakerpress"),
    route: "order_tax_rates",
    parameterConfig: {},
  },
  {
    name: __("Product Downloads", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 12,
    icon: Download,
    iconName: "download",
    description: __(
      "Generate downloadable files for products and grant download permissions on existing orders. Requires existing products.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Developers testing digital-product fulfillment", "fluent-cart-fakerpress"),
    route: "product_downloads",
    parameterConfig: {},
  },
  {
    name: __("Subscriptions", "fluent-cart-fakerpress"),
    category: __("Advanced", "fluent-cart-fakerpress"),
    order: 13,
    icon: Repeat,
    iconName: "repeat",
    description: __(
      "Generate subscription records against existing orders. The table ships in core; active billing requires Fluent Cart Pro. Requires existing orders and products.",
      "fluent-cart-fakerpress",
    ),
    useCase: __("Developers testing recurring-billing views and reports", "fluent-cart-fakerpress"),
    route: "subscriptions",
    parameterConfig: {},
  },
];

