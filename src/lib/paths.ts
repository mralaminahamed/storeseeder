/**
 * Dot-path utilities for reading and immutably updating nested objects.
 *
 * Example paths: "price_range", "inventory.manage_stock", "a.b.c"
 *
 * Typed with `unknown` rather than `any`: what comes back from a path is whatever
 * the generator's parameter schema put there, so the caller has to narrow it. The
 * container type is `ParamBag`, a tree of unknown leaves.
 */

/** A plain object holding generator parameters, nested to any depth. */
export type ParamBag = Record<string, unknown>;

/** Narrow an unknown to a plain object so its keys can be read. */
function isBag(value: unknown): value is ParamBag {
  return null !== value && "object" === typeof value;
}

/**
 * Read a value at a dot-separated path from a plain object.
 * Returns `undefined` if any segment along the path is missing.
 */
export function getPath(obj: ParamBag, path: string): unknown {
  let cursor: unknown = obj;
  for (const part of path.split(".")) {
    if (!isBag(cursor)) {
      return undefined;
    }
    cursor = cursor[part];
  }
  return cursor;
}

/**
 * Return a NEW object with the nested `path` set to `value`.
 * Intermediate objects are created as needed; existing sibling keys are
 * preserved via shallow-cloning at each level.
 */
export function setPath(obj: ParamBag, path: string, value: unknown): ParamBag {
  const parts = path.split(".");

  function recurse(current: ParamBag, segments: string[]): ParamBag {
    const [head, ...tail] = segments;
    if (0 === tail.length) {
      return { ...current, [head]: value };
    }
    const existing = current[head];
    const nested = isBag(existing) ? existing : {};
    return { ...current, [head]: recurse(nested, tail) };
  }

  return recurse(obj, parts);
}
