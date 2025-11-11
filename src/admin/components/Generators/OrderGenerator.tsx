import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function OrderGenerator() {
  const parameterConfig = {
    order_status: {
      description: __("Order status for generated orders", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["pending", "processing", "completed", "cancelled", "refunded"],
      },
      default: ["completed", "processing", "pending"],
    },
    payment_methods: {
      description: __("Payment methods to use for orders", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["stripe", "paypal", "cod", "bank_transfer", "check"],
      },
      default: ["stripe", "paypal", "cod"],
    },
    order_value_range: {
      description: __("Order total value range", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum order value", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 10,
        },
        max: {
          type: "number" as const,
          description: __("Maximum order value", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 1000,
        },
      },
    },
    items_per_order: {
      description: __("Number of items per order range", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum items per order", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 10,
          default: 1,
        },
        max: {
          type: "number" as const,
          description: __("Maximum items per order", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 20,
          default: 5,
        },
      },
    },
    include_customer: {
      description: __("Include customer data with orders", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    include_shipping: {
      description: __("Include shipping costs in orders", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    include_tax: {
      description: __("Include tax calculations in orders", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Orders", "fluent-cart-fakerpress")}
      description={__(
        "Create complete orders with customer data, items, payments, shipping, and tax calculations for testing Fluent Cart order processing and management systems.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/orders/generate"
      parameterConfig={parameterConfig}
    />
  );
}