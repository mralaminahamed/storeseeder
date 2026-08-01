import { describe, expect, it } from "@jest/globals";

import {
  CHUNK,
  isBlocked,
  planCalls,
  routeRows,
  runnableSteps,
  scaledCount,
  skippedSteps,
  totalRows,
  type Recipe,
  type RecipeStep,
} from "./recipes";

function step(resource: string, count: number, supported: boolean | null = true): RecipeStep {
  return { resource, count, supported, reason: "", extension: "", ignored_fields: [] };
}

function recipe(plan: RecipeStep[], over: Partial<Recipe> = {}): Recipe {
  return {
    id: "grocery",
    name: "Corner grocer",
    description: "",
    icon: "cart",
    accent: "green",
    locales: ["en_US"],
    plan,
    icon_uri: "",
    locale_shipped: true,
    fallback_locale: "en_US",
    issues: [],
    ...over,
  };
}

describe("recipe planning", () => {
  /**
   * The plan is a dependency order — brands and categories before products, products and
   * customers before orders — so anything that reorders it produces orders with no line items.
   */
  it("keeps the recipe's own order", () => {
    const calls = planCalls(
      recipe([step("brand", 10), step("product", 20), step("order", 30)]),
      "m",
    );

    expect(calls.map((c) => c.resource)).toEqual(["brand", "product", "order"]);
  });

  /**
   * The endpoint caps a request at 100 rows, so a big step is many calls. That is also what makes
   * progress honest: fifteen calls move the bar fifteen times.
   */
  it("chunks a step at the endpoint's cap", () => {
    const calls = planCalls(recipe([step("product", 250)]), "m");

    expect(calls).toHaveLength(3);
    expect(calls.map((c) => c.count)).toEqual([CHUNK, CHUNK, 50]);
  });

  it("never asks for more than the cap in one call", () => {
    const calls = planCalls(recipe([step("product_variation", 1440)]), "m");

    expect(calls.every((c) => c.count <= CHUNK)).toBe(true);
    expect(calls.reduce((t, c) => t + c.count, 0)).toBe(1440);
  });

  /** A refused resource never enters the loop — the card already said the driver will not take it. */
  it("leaves out what the platform refuses", () => {
    const calls = planCalls(
      recipe([step("product", 10), step("transaction", 900, false)]),
      "m",
    );

    expect(calls.map((c) => c.resource)).toEqual(["product"]);
  });

  /**
   * `null` is "no platform resolved yet", which is not a refusal. Filtering it out would leave an
   * unconfigured store looking like one where every resource is unsupported.
   */
  it("treats an unanswered resource as runnable", () => {
    expect(runnableSteps(recipe([step("product", 5, null)]))).toHaveLength(1);
    expect(skippedSteps(recipe([step("product", 5, null)]))).toHaveLength(0);
  });

  it("drops a resource no generator serves", () => {
    expect(planCalls(recipe([step("unicorn", 10)]), "m")).toEqual([]);
  });

  /**
   * The route is looked up, never derived: `cart_session` is served at `cart-sessions`, and no
   * singularisation rule survives `shipping_classes`.
   */
  it("sends each resource to its own route", () => {
    const calls = planCalls(recipe([step("cart_session", 5), step("tax_class", 5)]), "m");

    expect(calls.map((c) => c.route)).toEqual(["cart-sessions", "tax_classes"]);
  });
});

describe("recipe sizes", () => {
  it("scales the declared counts", () => {
    const s = step("product", 200);

    expect(scaledCount(s, "s")).toBe(50);
    expect(scaledCount(s, "m")).toBe(200);
    expect(scaledCount(s, "l")).toBe(800);
  });

  /** A step that creates nothing is a line of noise on the card. */
  it("never scales a step below one", () => {
    expect(scaledCount(step("coupon", 2), "s")).toBe(1);
  });

  it("falls back to Medium for an unknown size", () => {
    expect(scaledCount(step("product", 200), "xl")).toBe(200);
  });

  it("totals only what will run", () => {
    const r = recipe([step("product", 100), step("transaction", 900, false)]);

    expect(totalRows(r, "m")).toBe(100);
    expect(totalRows(r, "s")).toBe(25);
  });
});

describe("routeRows", () => {
  /**
   * The runner counts by resource because that is what a plan names; the stats store counts by
   * route. Converting in one place means only one of the two can be wrong.
   */
  it("re-keys a resource tally by route", () => {
    expect(routeRows({ product: 10, cart_session: 4 })).toEqual({
      products: 10,
      "cart-sessions": 4,
    });
  });

  it("drops a resource with no route rather than inventing one", () => {
    expect(routeRows({ unicorn: 10 })).toEqual({});
  });
});

describe("isBlocked", () => {
  /**
   * Only a recipe that would build a store misrepresenting itself is refused. Generic categories
   * are worth saying and not worth blocking.
   */
  it("blocks only on a blocking issue", () => {
    const soft = recipe([step("product", 5)], {
      issues: [{ code: "storeseeder_recipe_partial", message: "", blocking: false }],
    });
    const hard = recipe([step("product", 5)], {
      issues: [{ code: "storeseeder_recipe_no_vocabulary", message: "", blocking: true }],
    });

    expect(isBlocked(recipe([step("product", 5)]))).toBe(false);
    expect(isBlocked(soft)).toBe(false);
    expect(isBlocked(hard)).toBe(true);
  });
});
