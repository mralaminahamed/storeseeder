import { describe, expect, it } from "@jest/globals";

import { CHUNK, chunkCounts } from "./chunk";
import { CHUNK as RECIPES_CHUNK } from "./recipes";

/**
 * Splitting a count into requests the endpoint will accept.
 *
 * Written after a run of more than 100 failed outright: the generate route caps `count` at 100 in its
 * own schema, WordPress rejects the argument before the handler sees it, and both the single-run and
 * batch paths sent the requested total whole. `Invalid parameter(s): count`, nothing written — while
 * the stepper offered up to 100,000.
 */
describe("chunkCounts", () => {
  it("leaves a count within the cap as one request", () => {
    expect(chunkCounts(1)).toEqual([1]);
    expect(chunkCounts(10)).toEqual([10]);
    expect(chunkCounts(CHUNK)).toEqual([CHUNK]);
  });

  it("splits a count above the cap, remainder last", () => {
    expect(chunkCounts(250)).toEqual([100, 100, 50]);
    expect(chunkCounts(101)).toEqual([100, 1]);
  });

  it("divides evenly without a trailing zero", () => {
    expect(chunkCounts(300)).toEqual([100, 100, 100]);
  });

  it("never proposes a request the schema would reject", () => {
    for (const total of [1, 99, 100, 101, 250, 900, 5000]) {
      const counts = chunkCounts(total);

      // `minimum: 1` and `maximum: 100`, and the parts have to add up to what was asked for.
      expect(counts.every((n) => n >= 1 && n <= CHUNK)).toBe(true);
      expect(counts.reduce((a, b) => a + b, 0)).toBe(total);
    }
  });

  it("asks for nothing when there is nothing to ask for", () => {
    // Not `[0]`, which the schema rejects on `minimum: 1`.
    expect(chunkCounts(0)).toEqual([]);
    expect(chunkCounts(-5)).toEqual([]);
  });

  /**
   * The cap has to be one number. `recipes.ts` declared its own `CHUNK = 100` while the single-run and
   * batch paths had no notion of a cap at all; this asserts the agreement rather than the value, which
   * is the only kind of test that catches the two drifting.
   */
  it("is the same cap the recipe runner plans against", () => {
    expect(RECIPES_CHUNK).toBe(CHUNK);
  });
});
