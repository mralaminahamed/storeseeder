import { describe, expect, it } from "@jest/globals";

import type { StoredRun } from "@/types";
import { saveSettings } from "./settings";
import {
  addRun,
  clearStats,
  getCounts,
  getRecentRuns,
  getRuns,
  getStats,
  getTotalGenerated,
  incrementStats,
} from "./storage";

function run(overrides: Partial<StoredRun> = {}): StoredRun {
  return {
    count: 10,
    timestamp: 1_690_000_000_000,
    success: true,
    message: "Generated 10 products",
    ...overrides,
  };
}

describe("run history", () => {
  it("starts empty and records newest first", () => {
    expect(getRuns("products")).toEqual([]);

    addRun("products", run({ count: 1 }));
    addRun("products", run({ count: 2 }));

    expect(getRuns("products").map((r) => r.count)).toEqual([2, 1]);
  });

  it("keeps histories separate per generator", () => {
    addRun("products", run({ count: 1 }));
    addRun("orders", run({ count: 2 }));

    expect(getRuns("products")).toHaveLength(1);
    expect(getRuns("orders")).toHaveLength(1);
  });

  /**
   * The cap is the Run history setting, read at write time — so lowering it takes effect
   * on the next run rather than needing a migration.
   */
  it("trims to the configured maximum", () => {
    saveSettings({
      defaultCount: 10,
      defaultLocale: "en_US",
      defaultSeed: "",
      defaultIncludeMeta: true,
      maxRunsPerGenerator: 3,
    });

    for (let i = 1; i <= 6; i++) {
      addRun("products", run({ count: i }));
    }

    expect(getRuns("products").map((r) => r.count)).toEqual([6, 5, 4]);
  });

  it("reads corrupt history as empty rather than throwing", () => {
    localStorage.setItem("ec_fp_runs_products", "}{");

    expect(getRuns("products")).toEqual([]);
  });
});

describe("recent runs across generators", () => {
  it("interleaves routes, newest first, and carries locale and seed", () => {
    addRun("products", run(), { locale: "de_DE", seed: "42" });
    addRun("orders", run());

    const recent = getRecentRuns(10);

    expect(recent.map((r) => r.route)).toEqual(["orders", "products"]);
    expect(recent[1]).toMatchObject({ locale: "de_DE", seed: "42" });
  });

  it("omits locale and seed when the run had none, rather than storing undefined", () => {
    addRun("products", run());

    expect(getRecentRuns(1)[0]).not.toHaveProperty("locale");
  });

  it("respects the requested limit", () => {
    for (let i = 0; i < 5; i++) {
      addRun("products", run({ count: i }));
    }

    expect(getRecentRuns(2)).toHaveLength(2);
  });
});

describe("stats", () => {
  it("accumulates per route", () => {
    incrementStats("products", 10);
    incrementStats("products", 5);
    incrementStats("orders", 2);

    expect(getStats("products")).toBe(15);
    expect(getCounts()).toEqual({ products: 15, orders: 2 });
    expect(getTotalGenerated()).toBe(17);
  });

  it("reports zero for a route with no runs", () => {
    expect(getStats("products")).toBe(0);
    expect(getTotalGenerated()).toBe(0);
  });
});

describe("clearStats", () => {
  /**
   * The danger-zone button. It has to take the history and the counts, and leave the
   * user's preferences alone — clearing the theme or the settings while "clearing run
   * history" would be a surprise, and there is no undo.
   */
  it("removes history and stats but keeps unrelated keys", () => {
    addRun("products", run());
    incrementStats("products", 10);
    localStorage.setItem("fp_theme", "dark");
    localStorage.setItem("fp_density", "compact");
    saveSettings({
      defaultCount: 42,
      defaultLocale: "en_US",
      defaultSeed: "",
      defaultIncludeMeta: true,
      maxRunsPerGenerator: 10,
    });

    clearStats();

    expect(getRuns("products")).toEqual([]);
    expect(getCounts()).toEqual({});
    expect(getRecentRuns(10)).toEqual([]);

    expect(localStorage.getItem("fp_theme")).toBe("dark");
    expect(localStorage.getItem("fp_density")).toBe("compact");
    expect(localStorage.getItem("ec_fp_settings")).not.toBeNull();
  });
});
