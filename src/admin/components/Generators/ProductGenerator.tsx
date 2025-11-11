import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function ProductGenerator() {
  const parameterConfig = {
    product_type: {
      description: __("Type of products to generate", "fluent-cart-fakerpress"),
      type: "string" as const,
      enum: ["simple", "variable", "digital", "mixed"],
      default: "mixed",
    },
    price_range: {
      description: __(
        "Price range for generated products",
        "fluent-cart-fakerpress",
      ),
      type: "object" as const,
      properties: {
        min: { type: "number" as const, description: __("Minimum price", "fluent-cart-fakerpress"), minimum: 0, default: 10 },
        max: { type: "number" as const, description: __("Maximum price", "fluent-cart-fakerpress"), minimum: 1, default: 500 },
      },
    },
    include_inventory: {
      description: __(
        "Include inventory management for products",
        "fluent-cart-fakerpress",
      ),
      type: "boolean" as const,
      default: true,
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Products", "fluent-cart-fakerpress")}
      description={__(
        "Create fake products with random names, descriptions, prices, and inventory for testing Fluent Cart functionality.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/products/generate"
      parameterConfig={parameterConfig}
    />
  );
}
