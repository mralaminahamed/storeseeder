import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function LocationGenerator() {
  const parameterConfig = {
    regions: {
      description: __("Geographic regions to generate locations for", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: [
          "Americas",
          "Europe",
          "Asia",
          "Africa",
          "Oceania",
          "Northern America",
          "Western Europe",
          "Eastern Europe",
          "Southern Europe",
          "Northern Europe",
          "Southeast Asia",
          "East Asia",
          "South Asia",
          "Western Asia",
          "North Africa",
          "Sub-Saharan Africa",
          "Australia and New Zealand",
        ],
      },
      default: [],
    },
    countries: {
      description: __("Specific countries to generate (ISO2, ISO3, or full names)", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: { type: "string" as const },
      default: [],
    },
    max_countries: {
      description: __("Maximum number of countries to generate", "fluent-cart-fakerpress"),
      type: "number" as const,
      minimum: 1,
      maximum: 50,
      default: 10,
    },
    include_states: {
      description: __("Include states/provinces for countries", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    include_cities: {
      description: __("Include cities for states/provinces", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    cities_per_state: {
      description: __("Maximum cities per state/province", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum cities per state", "fluent-cart-fakerpress"),
          minimum: 1,
          default: 3,
        },
        max: {
          type: "number" as const,
          description: __("Maximum cities per state", "fluent-cart-fakerpress"),
          minimum: 1,
          maximum: 50,
          default: 15,
        },
      },
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Locations", "fluent-cart-fakerpress")}
      description={__(
        "Create geographic location data including countries, states/provinces, and cities for testing Fluent Cart shipping and tax calculations.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/locations/generate"
      parameterConfig={parameterConfig}
    />
  );
}