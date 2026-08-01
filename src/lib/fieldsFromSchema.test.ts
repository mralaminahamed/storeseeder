import { describe, expect, it } from "@jest/globals";

import type { ParameterConfig } from "@/types";
import {
  asParamValue,
  fieldsFromSchema,
  humanize,
  countryLabel,
  optionLabel,
} from "./fieldsFromSchema";
import { generators } from "./generators";

/**
 * This module is what makes the admin schema-driven: every control on every generator page
 * comes out of here, so a mapping mistake is seventeen broken forms rather than one. The
 * tests are the mapping table from its own docblock, asserted.
 */
describe("countryLabel", () => {
  it("names the country a code stands for", () => {
    expect(countryLabel("NL")).toBe("Netherlands");
    expect(countryLabel("JP")).toBe("Japan");
  });

  /**
   * `QQ` is unassigned in CLDR. (`ZZ` is not a good test: it is a real code meaning
   * "Unknown Region", and resolving it to that string is correct.)
   */
  it("returns an unassigned code unchanged", () => {
    expect(countryLabel("QQ")).toBe("QQ");
  });
});

describe("optionLabel", () => {
  /**
   * The bug: selects and chips drew the raw schema value, so a payment method read
   * `bank_transfer` and an order status `on_hold`.
   */
  it("turns an underscored value into words", () => {
    expect(optionLabel("bank_transfer")).toBe("Bank Transfer");
    expect(optionLabel("on_hold")).toBe("On Hold");
    expect(optionLabel("free_shipping")).toBe("Free Shipping");
  });

  it("capitalises a single word", () => {
    expect(optionLabel("percentage")).toBe("Percentage");
    expect(optionLabel("refunded")).toBe("Refunded");
  });

  /**
   * Age bands and sizes start with a digit and are already readable. Splitting them the way an
   * underscored value is split would turn "18-25" into "18 25".
   */
  it("leaves a value that starts with a digit alone", () => {
    expect(optionLabel("18-25")).toBe("18-25");
    expect(optionLabel("65+")).toBe("65+");
    expect(optionLabel("250g")).toBe("250g");
  });

  /**
   * A country picker offering "US" and "GB" is asking the reader to know the codes. The value sent
   * to the API is still the code.
   */
  it("resolves a two-letter country code to its country name", () => {
    expect(optionLabel("US")).toBe("United States");
    expect(optionLabel("GB")).toBe("United Kingdom");
    expect(optionLabel("DE")).toBe("Germany");
    expect(optionLabel("BR")).toBe("Brazil");
  });

  it("falls back to the code when it names no country", () => {
    expect(optionLabel("QQ")).toBe("QQ");
  });

  it("leaves a value the schema already wrote as a label", () => {
    expect(optionLabel("Color")).toBe("Color");
    expect(optionLabel("Image")).toBe("Image");
  });

  /**
   * The handful Title Case gets wrong. "Cod" is a fish; "Paypal" is not how the brand is spelled.
   */
  it("uses the override for values Title Case would mangle", () => {
    expect(optionLabel("cod")).toBe("Cash on Delivery");
    expect(optionLabel("paypal")).toBe("PayPal");
    expect(optionLabel("vip")).toBe("VIP");
    expect(optionLabel("authorize_net")).toBe("Authorize.Net");
  });

  it("does not choke on an empty value", () => {
    expect(optionLabel("")).toBe("");
  });
});

describe("humanize", () => {
  it.each([
    ["price_range", "Price Range"],
    ["manage-stock", "Manage Stock"],
    ["order_status", "Order Status"],
    ["simple", "Simple"],
    ["", ""],
    ["__leading_and_trailing__", "Leading And Trailing"],
  ])("%s → %s", (input, expected) => {
    expect(humanize(input)).toBe(expected);
  });
});

describe("asParamValue", () => {
  it("passes the primitives a control can render", () => {
    expect(asParamValue("text")).toBe("text");
    expect(asParamValue(7)).toBe(7);
    expect(asParamValue(false)).toBe(false);
  });

  it("treats null and undefined as empty", () => {
    expect(asParamValue(null)).toBeUndefined();
    expect(asParamValue(undefined)).toBeUndefined();
  });

  /**
   * Chips hold strings. A mixed array arrives from a schema default or a stale stored
   * value, and a number in it would be rendered as a chip that cannot be matched.
   */
  it("keeps only the strings in an array", () => {
    expect(asParamValue(["a", 1, "b", null, true])).toEqual(["a", "b"]);
  });

  it("recognises a {lo,hi} pair and rejects a partial one", () => {
    expect(asParamValue({ lo: 1, hi: 9 })).toEqual({ lo: 1, hi: 9 });
    expect(asParamValue({ lo: 1 })).toBeUndefined();
    expect(asParamValue({ lo: "1", hi: "9" })).toBeUndefined();
  });

  it("gives undefined for anything else, which every control renders as empty", () => {
    expect(asParamValue({ anything: "else" })).toBeUndefined();
    expect(asParamValue(() => undefined)).toBeUndefined();
  });
});

describe("fieldsFromSchema", () => {
  const fieldsOf = (config: Record<string, ParameterConfig>) =>
    fieldsFromSchema(config).flatMap((s) => s.fields);

  it("maps a string with an enum to a select", () => {
    const [field] = fieldsOf({
      order_status: { type: "string", enum: ["pending", "completed"] },
    });

    expect(field).toMatchObject({
      key: "order_status",
      type: "select",
      label: "Order Status",
      options: ["pending", "completed"],
    });
  });

  it("maps a string with no enum to text", () => {
    expect(fieldsOf({ note: { type: "string" } })[0].type).toBe("text");
  });

  it("maps an array to chips, taking options from items.enum", () => {
    const [field] = fieldsOf({
      payment_methods: {
        type: "array",
        items: { type: "string", enum: ["stripe", "paypal"], default: ["stripe"] },
      },
    });

    expect(field).toMatchObject({
      type: "chips",
      options: ["stripe", "paypal"],
      default: ["stripe"],
    });
  });

  it("gives chips an empty option list rather than undefined when the schema omits one", () => {
    expect(fieldsOf({ tags: { type: "array" } })[0].options).toEqual([]);
  });

  it("maps a boolean to a toggle, coercing the default", () => {
    const [field] = fieldsOf({ manage_stock: { type: "boolean" } });

    expect(field.type).toBe("toggle");
    // Never undefined: a toggle has to start somewhere, and off is the safe start.
    expect(field.default).toBe(false);
  });

  it("carries bounds through to a number field", () => {
    const [field] = fieldsOf({
      variation_count: { type: "integer", minimum: 1, maximum: 20, default: 3 },
    });

    expect(field).toMatchObject({ type: "number", min: 1, max: 20, default: 3 });
  });

  describe("an object of exactly {min,max}", () => {
    it("becomes one range field, not two numbers", () => {
      const fields = fieldsOf({
        price_range: {
          type: "object",
          properties: {
            min: { type: "number", default: 5 },
            max: { type: "number", default: 500 },
          },
        },
      });

      expect(fields).toHaveLength(1);
      expect(fields[0]).toMatchObject({ type: "range", min: 5, max: 500 });
    });

    it("falls back to the children's own bounds when they have no defaults", () => {
      const [field] = fieldsOf({
        span: {
          type: "object",
          properties: {
            min: { type: "integer", minimum: 2 },
            max: { type: "integer", maximum: 40 },
          },
        },
      });

      expect(field).toMatchObject({ min: 2, max: 40 });
    });

    it("is not a range when a third key is present", () => {
      const fields = fieldsOf({
        span: {
          type: "object",
          properties: {
            min: { type: "integer" },
            max: { type: "integer" },
            step: { type: "integer" },
          },
        },
      });

      expect(fields).toHaveLength(3);
      expect(fields.map((f) => f.type)).toEqual(["number", "number", "number"]);
    });

    it("is not a range when the pair is not numeric", () => {
      const [field] = fieldsOf({
        span: {
          type: "object",
          properties: {
            min: { type: "string" },
            max: { type: "string" },
          },
        },
      });

      expect(field.type).toBe("text");
    });
  });

  it("recurses any other object, prefixing child keys and sharing a section", () => {
    const sections = fieldsFromSchema({
      inventory: {
        type: "object",
        title: "Inventory",
        properties: {
          manage_stock: { type: "boolean" },
          stock_range: {
            type: "object",
            properties: {
              min: { type: "integer", default: 1 },
              max: { type: "integer", default: 99 },
            },
          },
        },
      },
    });

    expect(sections).toHaveLength(1);
    expect(sections[0].name).toBe("Inventory");
    expect(sections[0].fields.map((f) => f.key)).toEqual([
      "inventory.manage_stock",
      "inventory.stock_range",
    ]);
  });

  it("groups top-level fields by section, in declaration order", () => {
    const sections = fieldsFromSchema({
      product_type: { type: "string", enum: ["physical"] },
      inventory: {
        type: "object",
        title: "Inventory",
        properties: { manage_stock: { type: "boolean" } },
      },
    });

    expect(sections.map((s) => s.name)).toEqual(["Product Type", "Inventory"]);
  });

  it("carries dependsOn through so a conditional field can be hidden", () => {
    const [field] = fieldsOf({
      specific_customer_id: {
        type: "integer",
        dependsOn: { customer_type: "specific" },
      },
    });

    expect(field.dependsOn).toEqual({ customer_type: "specific" });
  });

  it("omits dependsOn entirely when the schema has none", () => {
    // Present-but-undefined would make every field look conditional to a `in` check.
    expect(fieldsOf({ note: { type: "string" } })[0]).not.toHaveProperty(
      "dependsOn",
    );
  });

  /**
   * The real payload, not a fixture: every shipped generator's schema has to produce
   * renderable fields, since a control with no type or label renders as nothing and the
   * page looks broken rather than erroring.
   */
  it("renders every shipped generator's schema without producing a malformed field", () => {
    const known = new Set([
      "entity",
      "select",
      "chips",
      "range",
      "toggle",
      "number",
      "text",
    ]);

    for (const generator of generators) {
      for (const section of fieldsFromSchema(generator.parameterConfig ?? {})) {
        expect(section.name).not.toBe("");

        for (const field of section.fields) {
          expect(known).toContain(field.type);
          expect(field.label).not.toBe("");
          expect(field.key).not.toBe("");
        }
      }
    }
  });
});
