import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function CouponGenerator() {
  const parameterConfig = {
    discount_types: {
      description: __("Types of discount coupons to generate", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["percentage", "fixed_amount", "free_shipping", "buy_x_get_y"],
      },
      default: ["percentage", "fixed_amount"],
    },
    discount_range: {
      description: __(
        "Discount value range",
        "fluent-cart-fakerpress",
      ),
      type: "object" as const,
      properties: {
        min_percentage: {
          type: "number" as const,
          description: __("Minimum percentage discount", "fluent-cart-fakerpress"),
          minimum: 5,
          maximum: 95,
          default: 10,
        },
        max_percentage: {
          type: "number" as const,
          description: __("Maximum percentage discount", "fluent-cart-fakerpress"),
          minimum: 5,
          maximum: 95,
          default: 50,
        },
        min_fixed: {
          type: "number" as const,
          description: __("Minimum fixed discount amount", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 5,
        },
        max_fixed: {
          type: "number" as const,
          description: __("Maximum fixed discount amount", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 100,
        },
      },
    },
    usage_limits: {
      description: __(
        "Usage limitation settings",
        "fluent-cart-fakerpress",
      ),
      type: "object" as const,
      properties: {
        set_usage_limits: {
          type: "boolean" as const,
          description: __("Enable usage limits", "fluent-cart-fakerpress"),
          default: true,
        },
        max_uses: {
          type: "number" as const,
          description: __("Maximum total uses", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 1000,
          default: 100,
        },
        max_uses_per_user: {
          type: "number" as const,
          description: __("Maximum uses per user", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 10,
          default: 1,
        },
      },
    },
    validity_period: {
      description: __(
        "Coupon validity period configuration",
        "fluent-cart-fakerpress",
      ),
      type: "object" as const,
      properties: {
        min_days: {
          type: "number" as const,
          description: __("Minimum validity period in days", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 365,
          default: 7,
        },
        max_days: {
          type: "number" as const,
          description: __("Maximum validity period in days", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 365,
          default: 90,
        },
      },
    },
    restrictions: {
      description: __(
        "Coupon usage restrictions",
        "fluent-cart-fakerpress",
      ),
      type: "object" as const,
      properties: {
        minimum_spend: {
          type: "boolean" as const,
          description: __("Require minimum spend", "fluent-cart-fakerpress"),
          default: true,
        },
        maximum_spend: {
          type: "boolean" as const,
          description: __("Set maximum spend limit", "fluent-cart-fakerpress"),
          default: false,
        },
        exclude_sale_items: {
          type: "boolean" as const,
          description: __("Exclude sale items", "fluent-cart-fakerpress"),
          default: false,
        },
        product_restrictions: {
          type: "boolean" as const,
          description: __("Apply product restrictions", "fluent-cart-fakerpress"),
          default: true,
        },
      },
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Coupons", "fluent-cart-fakerpress")}
      description={__(
        "Create discount coupons with various types, usage limits, validity periods, and restrictions for testing Fluent Cart promotional functionality.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/coupons/generate"
      parameterConfig={parameterConfig}
    />
  );
}