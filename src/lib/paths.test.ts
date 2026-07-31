import { describe, expect, it } from "@jest/globals";

import { getPath, setPath } from "./paths";

/**
 * Every generator field writes through these, addressed by the dot-path its schema
 * produced ("inventory.stock_range.min"). Immutability is the load-bearing part: the
 * params bag is React state, so a mutation in here is a render that never happens.
 */
describe("getPath", () => {
  const bag = {
    count: 10,
    inventory: { manage_stock: true, stock_range: { min: 1, max: 99 } },
    zero: 0,
    empty: "",
    nothing: null,
  };

  it("reads a top-level key", () => {
    expect(getPath(bag, "count")).toBe(10);
  });

  it("reads a nested key", () => {
    expect(getPath(bag, "inventory.stock_range.max")).toBe(99);
  });

  it("returns falsy values as themselves, not as undefined", () => {
    expect(getPath(bag, "zero")).toBe(0);
    expect(getPath(bag, "empty")).toBe("");
    expect(getPath(bag, "nothing")).toBeNull();
  });

  it("returns undefined for a missing key or a missing branch", () => {
    expect(getPath(bag, "nope")).toBeUndefined();
    expect(getPath(bag, "inventory.nope.deeper")).toBeUndefined();
  });

  it("returns undefined rather than throwing when a segment is not an object", () => {
    // "count" is a number, so "count.min" cannot exist — and a generator page asking for
    // it must not take the whole form down.
    expect(getPath(bag, "count.min")).toBeUndefined();
  });
});

describe("setPath", () => {
  it("sets a top-level key", () => {
    expect(setPath({ a: 1 }, "b", 2)).toEqual({ a: 1, b: 2 });
  });

  it("preserves siblings at every level", () => {
    const before = {
      count: 10,
      inventory: { manage_stock: true, stock_range: { min: 1, max: 99 } },
    };

    expect(setPath(before, "inventory.stock_range.min", 5)).toEqual({
      count: 10,
      inventory: { manage_stock: true, stock_range: { min: 5, max: 99 } },
    });
  });

  it("creates the intermediate objects it needs", () => {
    expect(setPath({}, "a.b.c", 1)).toEqual({ a: { b: { c: 1 } } });
  });

  it("replaces a non-object standing where a branch is needed", () => {
    expect(setPath({ a: 5 }, "a.b", 1)).toEqual({ a: { b: 1 } });
  });

  /**
   * The params bag is React state. Mutating it in place would leave the reference equal,
   * so the component would not re-render and the control would look stuck.
   */
  it("does not mutate the input, at any depth", () => {
    const nested = { min: 1, max: 99 };
    const before = { inventory: { stock_range: nested } };

    const after = setPath(before, "inventory.stock_range.min", 5);

    expect(before.inventory.stock_range).toBe(nested);
    expect(nested.min).toBe(1);
    expect(after).not.toBe(before);
    expect(after.inventory).not.toBe(before.inventory);
  });

  it("can store undefined and null without dropping the key", () => {
    expect(setPath({ a: 1 }, "a", undefined)).toEqual({ a: undefined });
    expect(setPath({}, "a.b", null)).toEqual({ a: { b: null } });
  });
});
