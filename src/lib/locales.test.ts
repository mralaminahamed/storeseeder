import { describe, expect, it } from "@jest/globals";

import {
  DEFAULT_LOCALE,
  defaultLocale,
  filterLocales,
  isSupportedLocale,
  localeLabel,
  localeLabelWithCode,
  localeMap,
  localeOptions,
} from "./locales";

/**
 * The locale helpers exist because of a specific bug: the picker stored display labels
 * while the REST API wanted codes, and offered locales the API would not accept. These
 * tests are aimed at that — codes in, codes out, and never inventing an option.
 */
describe("locales", () => {
  it("reads the list the server inlined", () => {
    expect(Object.keys(localeMap())).toHaveLength(5);
    expect(localeMap().ja_JP).toBe("Japanese (Japan)");
  });

  it("has no options at all when the server sent none", () => {
    delete window.storeseederApi;

    // Empty rather than a hardcoded fallback list: offering a locale the API has not
    // confirmed is how the two lists drifted apart in the first place.
    expect(localeOptions()).toEqual([]);
    expect(isSupportedLocale(DEFAULT_LOCALE)).toBe(false);
  });

  describe("defaultLocale", () => {
    it("prefers the server's default", () => {
      expect(defaultLocale()).toBe("en_US");
    });

    it("falls back to the site's resolved locale, then to en_US", () => {
      window.storeseederApi = {
        locale: { faker: "de_DE", allLocales: { de_DE: "German (Germany)" } },
      };
      expect(defaultLocale()).toBe("de_DE");

      window.storeseederApi = {};
      expect(defaultLocale()).toBe(DEFAULT_LOCALE);
    });
  });

  describe("localeOptions", () => {
    it("carries the code beside the label, sorted by label", () => {
      const options = localeOptions();

      expect(options.map((o) => o.label)).toEqual([
        "Bangla (Bangladesh)",
        "English (United States)",
        "French (France)",
        "German (Germany)",
        "Japanese (Japan)",
      ]);
      // The code travels with the option, which is what the picker sends.
      expect(options[0]).toEqual({
        code: "bn_BD",
        label: "Bangla (Bangladesh)",
      });
    });
  });

  describe("localeLabel", () => {
    it("labels a known code", () => {
      expect(localeLabel("fr_FR")).toBe("French (France)");
    });

    it("falls back to the code rather than rendering blank", () => {
      expect(localeLabel("xx_XX")).toBe("xx_XX");
    });
  });

  describe("localeLabelWithCode", () => {
    /**
     * For the run history, which stored `de_DE` and drew it verbatim. The code stays — it is what
     * the run was made with — but it is no longer the only thing on screen.
     */
    it("names the language and keeps the code", () => {
      expect(localeLabelWithCode("fr_FR")).toBe("French (fr_FR)");
    });

    /**
     * The server's label already brackets the region. Keeping both would render
     * "Arabic (Egypt) (ar_EG)", and the code carries the region anyway.
     */
    it("drops the region rather than bracketing twice", () => {
      expect(localeLabelWithCode("bn_BD")).toBe("Bangla (bn_BD)");
      expect(localeLabelWithCode("ja_JP")).toBe("Japanese (ja_JP)");
    });

    it("falls back to the bare code rather than an empty bracket", () => {
      expect(localeLabelWithCode("xx_XX")).toBe("xx_XX");
    });
  });

  describe("isSupportedLocale", () => {
    it.each<[string, boolean]>([
      ["en_US", true],
      ["ja_JP", true],
      ["xx_XX", false],
      ["", false],
    ])("%s → %s", (code, expected) => {
      expect(isSupportedLocale(code)).toBe(expected);
    });
  });

  describe("filterLocales", () => {
    const options = () => localeOptions();

    it("returns everything for an empty or whitespace query", () => {
      expect(filterLocales(options(), "")).toHaveLength(5);
      expect(filterLocales(options(), "   ")).toHaveLength(5);
    });

    it("matches the label, case-insensitively", () => {
      expect(filterLocales(options(), "bangla").map((o) => o.code)).toEqual([
        "bn_BD",
      ]);
      expect(filterLocales(options(), "GERMAN").map((o) => o.code)).toEqual([
        "de_DE",
      ]);
    });

    /**
     * Someone who knows the code types the code — it is faster than finding
     * "Japanese (Japan)" by eye in a list of seventy-five.
     */
    it("matches the code", () => {
      expect(filterLocales(options(), "ja_JP").map((o) => o.code)).toEqual([
        "ja_JP",
      ]);
      expect(filterLocales(options(), "_BD").map((o) => o.code)).toEqual([
        "bn_BD",
      ]);
    });

    it("matches a language prefix across regions", () => {
      expect(filterLocales(options(), "en").length).toBeGreaterThanOrEqual(2);
    });

    it("returns nothing for a query that matches nothing", () => {
      expect(filterLocales(options(), "zzzzz")).toEqual([]);
    });
  });
});
