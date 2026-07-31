import { describe, expect, it } from "@jest/globals";

import type { PlatformInfo, PlatformState } from "@/types";
import {
  AUTO,
  activePlatforms,
  capabilityFor,
  findPlatform,
  initialPlatformState,
  isPlatformState,
  parsePlatformState,
  targetLabel,
} from "./platform";

function platform(
  id: string,
  overrides: Partial<PlatformInfo> = {},
): PlatformInfo {
  return {
    id,
    label: id.replace(/(^|-)([a-z])/g, (_m, _s, c: string) => ` ${c.toUpperCase()}`).trim(),
    active: true,
    version: "1.0.0",
    supports: {
      product: { supported: true, reason: "", extension: "", ignored_fields: [] },
      subscription: {
        supported: false,
        reason: "Requires WooCommerce Subscriptions.",
        extension: "woocommerce-subscriptions",
        ignored_fields: [],
      },
    },
    fields: {},
    ...overrides,
  };
}

function state(overrides: Partial<PlatformState> = {}): PlatformState {
  return {
    platforms: [platform("fluent-cart")],
    stored: "",
    resolved: "fluent-cart",
    ambiguous: false,
    ...overrides,
  };
}

describe("initialPlatformState", () => {
  it("reads the state the server inlined", () => {
    window.storeseederApi = { platforms: state() };

    expect(initialPlatformState().resolved).toBe("fluent-cart");
  });

  /**
   * An older build inlines no platform payload, and the topbar renders before any fetch
   * could return — so the empty state has to be a valid one, not undefined.
   */
  it("falls back to an empty state rather than undefined", () => {
    delete window.storeseederApi;

    expect(initialPlatformState()).toEqual({
      platforms: [],
      stored: "",
      resolved: null,
      ambiguous: false,
    });
  });
});

describe("activePlatforms", () => {
  it("keeps only what is loaded right now", () => {
    const s = state({
      platforms: [
        platform("fluent-cart"),
        platform("woocommerce", { active: false, version: null }),
      ],
    });

    expect(activePlatforms(s).map((p) => p.id)).toEqual(["fluent-cart"]);
  });
});

describe("findPlatform", () => {
  it("finds by id", () => {
    expect(findPlatform(state(), "fluent-cart")?.label).toBe("Fluent Cart");
  });

  it("treats a null or unknown id as not found", () => {
    expect(findPlatform(state(), null)).toBeUndefined();
    expect(findPlatform(state(), "")).toBeUndefined();
    expect(findPlatform(state(), "nope")).toBeUndefined();
  });
});

describe("targetLabel", () => {
  /**
   * "Auto" alone says nothing about where rows are going, which is the one thing this
   * label exists to answer.
   */
  it("names what Auto resolved to", () => {
    expect(targetLabel(state(), AUTO)).toBe("Auto · Fluent Cart");
  });

  it("says plain Auto when nothing is resolved", () => {
    expect(targetLabel(state({ resolved: null }), AUTO)).toBe("Auto");
  });

  it("names an explicit selection directly", () => {
    expect(targetLabel(state(), "fluent-cart")).toBe("Fluent Cart");
  });

  it("falls back to the id for a selection it cannot find", () => {
    // A platform deactivated after being chosen: better to show its id than nothing.
    expect(targetLabel(state(), "gone-away")).toBe("gone-away");
  });

  it("treats an empty selection as Auto", () => {
    expect(targetLabel(state(), "")).toBe("Auto · Fluent Cart");
  });
});

describe("capabilityFor", () => {
  it("reports a supported resource", () => {
    expect(capabilityFor(state(), AUTO, "product")?.supported).toBe(true);
  });

  it("reports why an unsupported one is unsupported, and what would fix it", () => {
    const cap = capabilityFor(state(), AUTO, "subscription");

    expect(cap?.supported).toBe(false);
    expect(cap?.extension).toBe("woocommerce-subscriptions");
    // Actionable: the UI can say "install X" rather than dimming a tile in silence.
    expect(cap?.reason).toContain("Subscriptions");
  });

  /**
   * null is not "no". With no target chosen the caller must prompt for one; dimming the
   * generator instead would tell the user it cannot be generated at all.
   */
  it("returns null when there is no target yet", () => {
    expect(capabilityFor(state({ resolved: null }), AUTO, "product")).toBeNull();
  });

  it("defaults an unlisted resource to unsupported rather than assuming support", () => {
    const cap = capabilityFor(state(), AUTO, "not_a_resource");

    expect(cap?.supported).toBe(false);
    expect(cap?.extension).toBe("");
  });

  it("honours an explicit selection over the resolved default", () => {
    const s = state({
      platforms: [
        platform("fluent-cart"),
        platform("stub-cart", {
          supports: { product: { supported: false, reason: "No.", extension: "", ignored_fields: [] } },
        }),
      ],
    });

    expect(capabilityFor(s, "stub-cart", "product")?.supported).toBe(false);
    expect(capabilityFor(s, AUTO, "product")?.supported).toBe(true);
  });
});

/**
 * The guard exists because the provider used to assign a REST body straight into state.
 * Everything downstream reads `state.platforms` unchecked, so a body without it produced a
 * white screen from a request that looked successful.
 */
describe("isPlatformState", () => {
  it("accepts a real state", () => {
    expect(isPlatformState(state())).toBe(true);
  });

  it.each([
    ["an empty object", {}],
    ["an error envelope", { code: "rest_forbidden", message: "Sorry." }],
    ["platforms of the wrong type", { platforms: "none", stored: "" }],
    ["a missing stored value", { platforms: [] }],
    ["null", null],
    ["a string", "fluent-cart"],
    ["undefined", undefined],
  ])("rejects %s", (_name, value) => {
    expect(isPlatformState(value)).toBe(false);
  });
});

describe("parsePlatformState", () => {
  it("passes a real state through", () => {
    expect(parsePlatformState(state())?.resolved).toBe("fluent-cart");
  });

  /**
   * Null rather than an empty state: the caller already holds a usable one, and replacing
   * it with empty would blank the topbar and claim no platform is installed.
   */
  it("returns null for anything else", () => {
    expect(parsePlatformState({})).toBeNull();
    expect(parsePlatformState(undefined)).toBeNull();
  });
});
