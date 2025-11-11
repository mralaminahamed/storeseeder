import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function CustomerGenerator() {
  const parameterConfig = {
    customer_type: {
      description: __("Type of customers to generate", "fluent-cart-fakerpress"),
      type: "string" as const,
      enum: ["individual", "business", "mixed"],
      default: "mixed",
    },
    country_focus: {
      description: __(
        "Focus generation on specific countries (leave empty for global)",
        "fluent-cart-fakerpress",
      ),
      type: "array" as const,
      items: { type: "string" as const },
      default: [],
    },
    include_history: {
      description: __(
        "Include purchase history and loyalty data",
        "fluent-cart-fakerpress",
      ),
      type: "boolean" as const,
      default: true,
    },
    loyalty_tier_focus: {
      description: __(
        "Focus on specific loyalty tiers (leave empty for all)",
        "fluent-cart-fakerpress",
      ),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["bronze", "silver", "gold", "platinum"],
      },
      default: [],
    },
    account_status: {
      description: __("Account status for generated customers", "fluent-cart-fakerpress"),
      type: "string" as const,
      enum: ["active", "inactive", "pending"],
      default: "active",
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Customers", "fluent-cart-fakerpress")}
      description={__(
        "Create fake customer profiles with comprehensive personal information, billing/shipping addresses, preferences, purchase history, loyalty tiers, and engagement metrics for testing Fluent Cart customer management systems.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/customers/generate"
      parameterConfig={parameterConfig}
    />
  );
}