import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function TaxClassGenerator() {
  const parameterConfig = {
    tax_types: {
      description: __("Types of tax classes to generate", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["standard", "reduced", "zero", "exempt", "digital"],
      },
      default: ["standard", "reduced", "zero"],
    },
    jurisdictions: {
      description: __("Tax jurisdictions to generate rates for", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["country", "state", "city", "county", "postcode"],
      },
      default: ["country", "state"],
    },
    rate_ranges: {
      description: __("Tax rate ranges by type", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        standard: {
          type: "object" as const,
          description: __("Standard tax rate range", "fluent-cart-fakerpress"),
          properties: {
            min: {
              type: "number" as const,
              description: __("Minimum standard tax rate", "fluent-cart-fakerpress"),
              minimum: 0,
              maximum: 50,
              default: 5,
            },
            max: {
              type: "number" as const,
              description: __("Maximum standard tax rate", "fluent-cart-fakerpress"),
              minimum: 0,
              maximum: 50,
              default: 25,
            },
          },
        },
        reduced: {
          type: "object" as const,
          description: __("Reduced tax rate range", "fluent-cart-fakerpress"),
          properties: {
            min: {
              type: "number" as const,
              description: __("Minimum reduced tax rate", "fluent-cart-fakerpress"),
              minimum: 0,
              maximum: 20,
              default: 1,
            },
            max: {
              type: "number" as const,
              description: __("Maximum reduced tax rate", "fluent-cart-fakerpress"),
              minimum: 0,
              maximum: 20,
              default: 10,
            },
          },
        },
      },
    },
    location_coverage: {
      description: __("Geographic coverage for tax rates", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        countries: {
          type: "array" as const,
          description: __("Countries to generate tax rates for", "fluent-cart-fakerpress"),
          items: { type: "string" as const },
          default: ["US", "CA", "GB", "AU", "DE"],
        },
        include_compound: {
          type: "boolean" as const,
          description: __("Include compound tax rates", "fluent-cart-fakerpress"),
          default: true,
        },
      },
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Tax Classes", "fluent-cart-fakerpress")}
      description={__(
        "Create tax classes with location-based rates and jurisdictions for testing Fluent Cart tax calculation and compliance systems.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/tax_classes/generate"
      parameterConfig={parameterConfig}
    />
  );
}