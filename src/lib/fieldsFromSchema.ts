import { __ } from "@wordpress/i18n";

import type { ParameterConfig, ParamValue } from "@/types";

export type FieldType =
  | "toggle"
  | "chips"
  | "range"
  | "select"
  | "number"
  | "text";

export interface FieldDescriptor {
  /** Dot-path into the params object, e.g. "price_range" or "inventory.manage_stock" */
  key: string;
  type: FieldType;
  label: string;
  section: string;
  /** Available options for select / chips fields */
  options?: string[];
  /** Lower bound for range / number fields */
  min?: number;
  /** Upper bound for range / number fields */
  max?: number;
  prefix?: string;
  suffix?: string;
  /** Placeholder for text fields. */
  ph?: string;
  default?: unknown;
  /** If present the render layer should hide this field unless the condition is met */
  dependsOn?: Record<string, unknown>;
}

export interface FieldSection {
  name: string;
  fields: FieldDescriptor[];
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Convert a snake_case or kebab-case identifier to Title Case.
 * e.g. "price_range" → "Price Range", "manage-stock" → "Manage Stock"
 */
export function humanize(key: string): string {
  return key
    .split(/[_\-]/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

/**
 * Option values that Title Case gets wrong.
 *
 * Everything else in the enums reads correctly once the underscores are spaces, so this stays
 * short on purpose — a lookup table of every value would be a second copy of the schema, and the
 * next person to add an enum would have to know to update it.
 */
const OPTION_LABELS: Record<string, string> = {
  cod: __("Cash on Delivery", "storeseeder"),
  // Proper nouns, through gettext anyway: a locale that transliterates them should be able to.
  paypal: __("PayPal", "storeseeder"),
  authorize_net: __("Authorize.Net", "storeseeder"),
  vip: __("VIP", "storeseeder"),
};

/**
 * The admin's own language, for resolving country names into.
 *
 * WordPress puts the site language on `<html lang>`; the browser's is the fallback, and English
 * the last resort. Read per call rather than cached — cheap, and a cached value would be wrong on
 * the one screen that can change it.
 */
function displayLocale(): string {
  return document.documentElement.lang || navigator.language || "en";
}

/**
 * The country a two-letter code names, in the admin's language.
 *
 * `Intl.DisplayNames` rather than a table: a hardcoded map would cover only the twelve codes the
 * schema happens to list today, in English only, and would need editing every time a driver adds a
 * country. Returns the code unchanged if the runtime cannot resolve it, so nothing renders blank.
 */
export function countryLabel(code: string): string {
  try {
    return (
      new Intl.DisplayNames([displayLocale()], {
        type: "region",
        // Explicit, though it is the default: an unassigned code comes back as itself rather
        // than as undefined.
        fallback: "code",
      }).of(code) ?? code
    );
  } catch {
    return code;
  }
}

/**
 * The label to show for one option value.
 *
 * Selects and chips were rendering the raw value: a payment method read `bank_transfer`, an order
 * status `on_hold`, a coupon type `free_shipping`. The value still travels to the API — only what
 * is drawn changes.
 *
 * Two things are left alone rather than titled:
 * - anything starting with a digit, because `18-25` and `65+` are already readable and splitting
 *   them on the hyphen would produce "18 25";
 * - values that already carry a capital, which the schema author wrote as a label.
 *
 * A two-letter uppercase code is resolved to its country name instead.
 *
 * Only underscores separate words here. Hyphens do not, for the age-band reason above.
 */
export function optionLabel(value: string): string {
  if (OPTION_LABELS[value]) return OPTION_LABELS[value];

  if (/^\d/.test(value)) return value;
  // A two-letter uppercase code is a country everywhere the schema uses one — "US" is not a word,
  // and "United States" is what a reader is choosing between.
  if (/^[A-Z]{2}$/.test(value)) return countryLabel(value);
  if (/[A-Z]/.test(value)) return value;

  return value
    .split("_")
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

/**
 * A schema `default` is untyped JSON, so a numeric bound has to be checked before
 * it can be used as one. Returns undefined for anything that is not a real number.
 */
function asNumber(value: unknown): number | undefined {
  return "number" === typeof value && Number.isFinite(value) ? value : undefined;
}

/**
 * Narrow a value read out of the params bag to something a field control accepts.
 *
 * The bag is a tree of unknowns — it is built from schema defaults and then
 * written to by whichever control the user touched — so this is the one place
 * that decides what a control may receive. Anything unrecognised becomes
 * undefined, which every control renders as its empty state.
 */
export function asParamValue(value: unknown): ParamValue {
  if (null === value || undefined === value) {
    return undefined;
  }

  if (
    "string" === typeof value ||
    "number" === typeof value ||
    "boolean" === typeof value
  ) {
    return value;
  }

  if (Array.isArray(value)) {
    return value.filter((entry): entry is string => "string" === typeof entry);
  }

  const pair = value as Record<string, unknown>;
  if ("number" === typeof pair.lo && "number" === typeof pair.hi) {
    return { lo: pair.lo, hi: pair.hi };
  }

  return undefined;
}

/**
 * Return true when the `properties` object is EXACTLY `{ min, max }` and both
 * are numeric (type "integer" | "number").
 */
function isMinMaxRange(
  properties: Record<string, ParameterConfig>,
): boolean {
  const keys = Object.keys(properties);
  if (keys.length !== 2 || !properties.min || !properties.max) return false;
  const numericTypes = new Set(["integer", "number"]);
  return (
    numericTypes.has(properties.min.type) &&
    numericTypes.has(properties.max.type)
  );
}

/**
 * Derive a single FieldDescriptor (or an array of them when the schema node
 * is an object that must be recursed) for one `key → config` entry.
 *
 * @param key     - The dot-path key for this field (already includes parent prefix)
 * @param config  - The ParameterConfig node
 * @param section - The section name this field belongs to
 */
function descriptorFromNode(
  key: string,
  config: ParameterConfig,
  section: string,
): FieldDescriptor | FieldDescriptor[] {
  const label = config.title ?? humanize(key.split(".").pop()!);

  // ── string + enum → select ─────────────────────────────────────────────
  if (config.type === "string" && config.enum) {
    return {
      key,
      type: "select",
      label,
      section,
      options: config.enum,
      default: config.default,
      ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
    };
  }

  // ── array → chips ─────────────────────────────────────────────────────
  if (config.type === "array") {
    return {
      key,
      type: "chips",
      label,
      section,
      options: config.items?.enum ?? [],
      default: config.default ?? config.items?.default ?? [],
      ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
    };
  }

  // ── object ─────────────────────────────────────────────────────────────
  if (config.type === "object" && config.properties) {
    // Exact { min, max } numeric → range
    if (isMinMaxRange(config.properties)) {
      const minProp = config.properties.min;
      const maxProp = config.properties.max;
      return {
        key,
        type: "range",
        label,
        section,
        min: asNumber(minProp.default) ?? minProp.minimum,
        max: asNumber(maxProp.default) ?? maxProp.maximum,
        default: config.default,
        ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
      };
    }

    // Any other object shape → recurse into properties
    const childSection = config.title ?? humanize(key.split(".").pop()!);
    const children: FieldDescriptor[] = [];
    for (const [childKey, childConfig] of Object.entries(config.properties)) {
      const fullKey = `${key}.${childKey}`;
      const result = descriptorFromNode(fullKey, childConfig, childSection);
      if (Array.isArray(result)) {
        children.push(...result);
      } else {
        children.push(result);
      }
    }
    return children;
  }

  // ── boolean → toggle ──────────────────────────────────────────────────
  if (config.type === "boolean") {
    return {
      key,
      type: "toggle",
      label,
      section,
      default: !!config.default,
      ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
    };
  }

  // ── integer / number (no enum) → number ───────────────────────────────
  if (config.type === "integer" || config.type === "number") {
    return {
      key,
      type: "number",
      label,
      section,
      ...(config.minimum !== undefined ? { min: config.minimum } : {}),
      ...(config.maximum !== undefined ? { max: config.maximum } : {}),
      default: config.default,
      ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
    };
  }

  // ── string (no enum) / anything else → text ───────────────────────────
  return {
    key,
    type: "text",
    label,
    section,
    default: config.default,
    ...(config.dependsOn ? { dependsOn: config.dependsOn } : {}),
  };
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Convert a generator's `parameterConfig` map into an ordered list of
 * FieldSections, each containing typed FieldDescriptors ready for rendering.
 *
 * Mapping rules
 * - string + enum       → select
 * - array               → chips  (options = items.enum ?? [])
 * - object {min, max}   → range  (min/max come from the child defaults/minimums)
 * - object (other)      → recurse; children share the parent's humanized name
 *                         as their section
 * - boolean             → toggle
 * - integer / number    → number  (carries min/max/default)
 * - string (no enum)    → text
 */
export function fieldsFromSchema(
  config: Record<string, ParameterConfig>,
): FieldSection[] {
  /** Map from section name → fields collected for that section */
  const sectionMap = new Map<string, FieldDescriptor[]>();

  const pushField = (field: FieldDescriptor): void => {
    const bucket = sectionMap.get(field.section);
    if (bucket) {
      bucket.push(field);
    } else {
      sectionMap.set(field.section, [field]);
    }
  };

  for (const [key, nodeConfig] of Object.entries(config)) {
    // Section for top-level entry: use title or humanize the key
    const topSection = nodeConfig.title ?? humanize(key);

    const result = descriptorFromNode(key, nodeConfig, topSection);

    if (Array.isArray(result)) {
      // Object that was recursed: all child fields already carry their own
      // section (= humanized parent key), but we want them grouped under the
      // top-level parent's section name so they stay together.
      // The recursed section is already set to the humanized parent key by
      // descriptorFromNode, which is exactly what we want.
      result.forEach(pushField);
    } else {
      pushField(result);
    }
  }

  return Array.from(sectionMap.entries()).map(([name, fields]) => ({
    name,
    fields,
  }));
}
