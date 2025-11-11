import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function ShippingPlanGenerator() {
  const parameterConfig = {
    shipping_types: {
      description: __("Types of shipping methods to generate", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["standard", "express", "overnight", "pickup", "free", "weight_based", "flat_rate"],
      },
      default: ["standard", "express", "free"],
    },
    cost_range: {
      description: __("Shipping cost range", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum shipping cost", "fluent-cart-fakerpress"),
          minimum: 0,
          default: 0,
        },
        max: {
          type: "number" as const,
          description: __("Maximum shipping cost", "fluent-cart-fakerpress"),
          minimum: 0,
          default: 50,
        },
      },
    },
    delivery_timeframes: {
      description: __("Delivery time ranges", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min_days: {
          type: "number" as const,
          description: __("Minimum delivery days", "fluent-cart-fakerpress"),
          minimum: 0,
          default: 1,
        },
        max_days: {
          type: "number" as const,
          description: __("Maximum delivery days", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 14,
        },
      },
    },
    coverage_areas: {
      description: __("Geographic coverage areas", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["domestic", "international", "regional", "worldwide"],
      },
      default: ["domestic", "international"],
    },
    calculation_methods: {
      description: __("Shipping calculation methods", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["flat_rate", "weight_based", "price_based", "quantity_based"],
      },
      default: ["flat_rate", "weight_based"],
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Shipping Plans", "fluent-cart-fakerpress")}
      description={__(
        "Create shipping plans with various methods, costs, and delivery timeframes for testing Fluent Cart shipping calculations and checkout processes.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/shipping-plans/generate"
      parameterConfig={parameterConfig}
    />
  );
}