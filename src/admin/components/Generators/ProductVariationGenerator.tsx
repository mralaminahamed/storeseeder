import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function ProductVariationGenerator() {
  const parameterConfig = {
    variation_types: {
      description: __("Types of product variations to generate", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["size", "color", "material", "style", "flavor", "weight", "dimension"],
      },
      default: ["size", "color"],
    },
    attributes_per_product: {
      description: __("Number of attributes per variable product", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum attributes per product", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 5,
          default: 1,
        },
        max: {
          type: "number" as const,
          description: __("Maximum attributes per product", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 5,
          default: 3,
        },
      },
    },
    variations_per_attribute: {
      description: __("Number of variations per attribute", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum variations per attribute", "fluent-cart-fakerpress"),
          minimum: 2,
          maximum: 10,
          default: 3,
        },
        max: {
          type: "number" as const,
          description: __("Maximum variations per attribute", "fluent-cart-fakerpress"),
          minimum: 2,
          maximum: 20,
          default: 8,
        },
      },
    },
    price_variation_range: {
      description: __("Price variation range as percentage of base price", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min_percentage: {
          type: "number" as const,
          description: __("Minimum price variation percentage", "fluent-cart-fakerpress"),
          minimum: -50,
          maximum: 0,
          default: -20,
        },
        max_percentage: {
          type: "number" as const,
          description: __("Maximum price variation percentage", "fluent-cart-fakerpress"),
          minimum: 0,
          maximum: 200,
          default: 50,
        },
      },
    },
    include_inventory: {
      description: __("Include inventory management for variations", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    generate_skus: {
      description: __("Generate unique SKUs for each variation", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Product Variations", "fluent-cart-fakerpress")}
      description={__(
        "Create product variations with attributes, pricing variations, and inventory management for testing Fluent Cart variable product functionality.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/product-variations/generate"
      parameterConfig={parameterConfig}
    />
  );
}