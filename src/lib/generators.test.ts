import { describe, expect, it } from "@jest/globals";

import { ICON_NAMES } from "./icons";
import {
  CATEGORY_ORDER,
  categoryLabel,
  generators,
  generatorsByCategory,
  sortedGenerators,
  usedCategories,
} from "./generators";

/**
 * What a generator's prerequisites are, taken from its own declared dependencies.
 *
 * Kept here rather than in the data because it is a property of the *store*, not of the
 * admin: orders need products, refunds need transactions. It is the graph documented in
 * docs/usage.md, and the sidebar order has to respect it.
 */
const PREREQUISITES: Record<string, string[]> = {
  product_variation: ["product"],
  attribute: ["product"],
  product_download: ["product", "order"],
  license: ["product", "order"],
  cart_session: ["product"],
  transaction: ["order"],
  refund: ["transaction"],
  subscription: ["order", "product"],
  label: ["order", "customer"],
  order_tax_rate: ["order", "tax_class"],
  order: ["product", "customer"],
};

describe("generators", () => {
  it("ships eighteen", () => {
    expect(generators).toHaveLength(18);
  });

  it("has a unique route and resource per generator", () => {
    const routes = generators.map((g) => g.route);
    const resources = generators.map((g) => g.resource);

    expect(new Set(routes).size).toBe(routes.length);
    expect(new Set(resources).size).toBe(resources.length);
  });

  /**
   * REST bases and canonical resource names are different key spaces — `cart-sessions`
   * serves `cart_session`, and no singularisation rule survives `shipping_classes`. Both
   * are declared rather than derived, so this only checks neither is missing.
   */
  it("declares both a route and a resource, never deriving one from the other", () => {
    for (const g of generators) {
      expect(g.route).not.toBe("");
      expect(g.resource).not.toBe("");
      expect(g.resource).not.toContain("-");
    }
  });

  it("names an icon that exists", () => {
    for (const g of generators) {
      // A name with no entry silently renders `box`, so a typo is invisible in the UI.
      expect(ICON_NAMES).toContain(g.iconName);
    }
  });

  it("uses only known categories", () => {
    for (const g of generators) {
      expect(CATEGORY_ORDER).toContain(g.category);
    }
  });

  /**
   * `category` holds a stable key, not a translated string. It used to hold `__("Core")`
   * while the sidebar filtered on the literal "Core", so every group came out empty on a
   * translated site.
   */
  it("keys categories with untranslated strings", () => {
    expect(generators.map((g) => g.category)).toContain("Core");
    expect(categoryLabel("Core")).toBe("Core");
    expect(categoryLabel("Nonsense")).toBe("Nonsense");
  });
});

describe("sortedGenerators", () => {
  it("orders by category first, then by order within it", () => {
    const sorted = sortedGenerators();

    // Written out rather than spread from Array.fill, whose element type is `any`.
    const expected = sorted.map((g) =>
      "products" === g.route ||
      "customers" === g.route ||
      "orders" === g.route ||
      "coupons" === g.route
        ? "Core"
        : "Advanced",
    );

    expect(sorted.map((g) => g.category)).toEqual(expected);
    expect(expected.filter((c) => "Core" === c)).toHaveLength(4);
    expect(expected.slice(0, 4).every((c) => "Core" === c)).toBe(true);
    expect(sorted[0].route).toBe("products");
  });

  it("returns a copy, leaving the exported array alone", () => {
    const before = generators.map((g) => g.route);
    sortedGenerators();

    expect(generators.map((g) => g.route)).toEqual(before);
  });

  /**
   * The load-bearing one. The sidebar, the dashboard grid and the command palette all
   * show this order, and it doubles as a "generate top to bottom" sequence — so no
   * generator may appear before something it needs. Regrouping Advanced by family has to
   * keep that true, and a future reorder that breaks it fails here rather than in a
   * user's store.
   */
  it("never places a generator before its prerequisites", () => {
    const position = new Map(
      sortedGenerators().map((g, i) => [g.resource, i] as const),
    );

    for (const [resource, needs] of Object.entries(PREREQUISITES)) {
      const at = position.get(resource);
      expect(at).toBeDefined();

      for (const need of needs) {
        const needAt = position.get(need);
        expect(needAt).toBeDefined();
        expect(needAt as number).toBeLessThan(at as number);
      }
    }
  });

  /**
   * Grouping by family is the other half of the rule, and the one that regressed: before
   * this, Shipping Plans sat at 2 with Shipping Classes at 9.
   */
  it("keeps each family contiguous within Advanced", () => {
    const advanced = sortedGenerators()
      .filter((g) => "Advanced" === g.category)
      .map((g) => g.resource);

    const families = [
      ["product_variation", "attribute", "product_download", "cart_session"],
      ["transaction", "refund", "subscription", "license", "label"],
      ["tax_class", "order_tax_rate"],
      ["shipping_plan", "shipping_class"],
    ];

    for (const family of families) {
      const indices = family.map((r) => advanced.indexOf(r));

      expect(indices).not.toContain(-1);
      // Contiguous: the span covered equals the number of members.
      expect(Math.max(...indices) - Math.min(...indices)).toBe(
        family.length - 1,
      );
    }
  });
});

describe("generatorsByCategory", () => {
  it("groups in CATEGORY_ORDER and accounts for every generator", () => {
    const groups = generatorsByCategory();

    expect(groups.map((g) => g.category)).toEqual(["Core", "Advanced"]);
    expect(groups.flatMap((g) => g.items)).toHaveLength(18);
  });

  it("labels each group for display", () => {
    expect(generatorsByCategory()[0].label).toBe("Core");
  });

  it("omits a category with no generators", () => {
    // Enhanced is declared in the order but ships nothing, so it must not render as an
    // empty heading.
    expect(usedCategories()).not.toContain("Enhanced");
  });
});
