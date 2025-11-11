import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function CartSessionGenerator() {
  const parameterConfig = {
    customer_type: {
      description: __("Type of customers for cart sessions", "fluent-cart-fakerpress"),
      type: "string" as const,
      enum: ["existing", "new", "mixed", "specific", "guest_only"],
      default: "mixed",
    },
    specific_customer_id: {
      description: __("Specific customer ID for cart sessions (when customer_type is 'specific')", "fluent-cart-fakerpress"),
      type: "number" as const,
      minimum: 1,
    },
    guest_cart_ratio: {
      description: __("Percentage of guest carts (0-100) when customer_type is 'mixed'", "fluent-cart-fakerpress"),
      type: "number" as const,
      minimum: 0,
      maximum: 100,
      default: 40,
    },
    abandonment_rate: {
      description: __("Cart abandonment rate percentage (0-100)", "fluent-cart-fakerpress"),
      type: "number" as const,
      minimum: 0,
      maximum: 100,
      default: 30,
    },
    status_distribution: {
      description: __("Custom cart status distribution", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        pending: {
          type: "number" as const,
          description: __("Percentage of pending carts", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 100,
        },
        abandoned: {
          type: "number" as const,
          description: __("Percentage of abandoned carts", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 100,
        },
        completed: {
          type: "number" as const,
          description: __("Percentage of completed carts", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 100,
        },
        cancelled: {
          type: "number" as const,
          description: __("Percentage of cancelled carts", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 100,
        },
      },
    },
    cart_value_range: {
      description: __("Cart value range for generated sessions", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum cart value", "fluent-cart-fakerpress"),
          minimum: 0,
          default: 5,
        },
        max: {
          type: "number" as const,
          description: __("Maximum cart value", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 500,
        },
      },
    },
    items_per_cart: {
      description: __("Number of items per cart session", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum items per cart", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 1,
        },
        max: {
          type: "number" as const,
          description: __("Maximum items per cart", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 15,
          default: 5,
        },
      },
    },
    abandonment_tracking: {
      description: __("Abandonment tracking settings", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        generate_reminders: {
          type: "boolean" as const,
          description: __("Generate abandoned cart reminders", "fluent-cart-fakerpress"),
          default: true,
        },
        reminder_count: {
          type: "number" as const,
          description: __("Maximum number of reminders to generate", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 10,
          default: 3,
        },
        recovery_rate: {
          type: "number" as const,
          description: __("Cart recovery rate percentage", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 100,
          default: 15,
        },
      },
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Cart Sessions", "fluent-cart-fakerpress")}
      description={__(
        "Create shopping cart sessions with items, customer data, and abandonment tracking for testing Fluent Cart cart functionality and recovery systems.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/cart-sessions/generate"
      parameterConfig={parameterConfig}
    />
  );
}