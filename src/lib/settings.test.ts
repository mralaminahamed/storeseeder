import { describe, expect, it, jest } from "@jest/globals";

import { getSettings, saveSettings } from "./settings";

const KEY = "ec_fp_settings";

/**
 * These tests are about one decision: stored settings are **validated field by field on
 * read**, not trusted. An earlier version spread `JSON.parse(raw)` wholesale, so anything
 * localStorage happened to hold became an AppSettings by assertion — a `defaultCount` left
 * as the string "10" by an older build would have flowed straight into arithmetic.
 */
describe("getSettings", () => {
  it("returns defaults with nothing stored", () => {
    expect(getSettings()).toEqual({
      defaultCount: 10,
      defaultLocale: "en_US",
      defaultSeed: "",
      defaultIncludeMeta: true,
      maxRunsPerGenerator: 10,
    });
  });

  it("takes the site's resolved locale as the default locale", () => {
    window.storeseederApi = { locale: { faker: "ja_JP" } };

    expect(getSettings().defaultLocale).toBe("ja_JP");
  });

  it("merges stored values over the defaults", () => {
    saveSettings({
      defaultCount: 42,
      defaultLocale: "de_DE",
      defaultSeed: "1234",
      defaultIncludeMeta: false,
      maxRunsPerGenerator: 25,
    });

    expect(getSettings()).toEqual({
      defaultCount: 42,
      defaultLocale: "de_DE",
      defaultSeed: "1234",
      defaultIncludeMeta: false,
      maxRunsPerGenerator: 25,
    });
  });

  it("keeps the fields it can use and defaults the rest", () => {
    localStorage.setItem(
      KEY,
      JSON.stringify({ defaultCount: 7, unknownField: "ignored" }),
    );

    const settings = getSettings();

    expect(settings.defaultCount).toBe(7);
    expect(settings.defaultIncludeMeta).toBe(true);
    expect(settings).not.toHaveProperty("unknownField");
  });

  describe("a stored value of the wrong type", () => {
    it.each([
      ["a numeric string where a number belongs", { defaultCount: "10" }, "defaultCount", 10],
      ["NaN", { defaultCount: Number.NaN }, "defaultCount", 10],
      ["Infinity", { maxRunsPerGenerator: Number.POSITIVE_INFINITY }, "maxRunsPerGenerator", 10],
      ["a number where a string belongs", { defaultSeed: 99 }, "defaultSeed", ""],
      ["a string where a boolean belongs", { defaultIncludeMeta: "yes" }, "defaultIncludeMeta", true],
      ["null", { defaultLocale: null }, "defaultLocale", "en_US"],
    ])("falls back for %s", (_name, stored, field, expected) => {
      // JSON cannot carry NaN or Infinity, so write the raw object the way a stale build
      // or a hand-edit would leave it.
      localStorage.setItem(KEY, JSON.stringify(stored));

      expect(getSettings()[field as keyof ReturnType<typeof getSettings>]).toBe(
        expected,
      );
    });
  });

  describe("unusable storage", () => {
    it.each([
      ["not JSON at all", "}{"],
      ["JSON that is not an object", '"a string"'],
      ["null", "null"],
      ["an array", "[1,2,3]"],
    ])("returns defaults for %s", (_name, raw) => {
      localStorage.setItem(KEY, raw);

      // An array passes `typeof === "object"`, so it reaches the field-by-field merge and
      // every field falls back — which is the right answer, not a crash.
      expect(getSettings().defaultCount).toBe(10);
    });
  });
});

describe("saveSettings", () => {
  it("round-trips through localStorage", () => {
    const settings = { ...getSettings(), defaultCount: 3 };
    saveSettings(settings);

    expect(JSON.parse(localStorage.getItem(KEY) ?? "{}")).toEqual(settings);
  });

  /**
   * Private browsing and a full quota both make setItem throw. Losing a preference is
   * acceptable; taking the settings page down with it is not.
   */
  it("swallows a storage failure rather than throwing at the caller", () => {
    const setItem = jest
      .spyOn(Storage.prototype, "setItem")
      .mockImplementation(() => {
        throw new DOMException("QuotaExceededError");
      });

    expect(() => saveSettings(getSettings())).not.toThrow();

    setItem.mockRestore();
  });
});
