import type { IconName } from "@/lib/icons";

declare global {
  interface Window {
    storeseederApi?: {
      restUrl?: string;
      restNonce?: string;
      version?: string;
      ajaxUrl?: string;
      /** The site's front page, for a link to the store that was just built. */
      homeUrl?: string;
      adminColors?: Record<string, string>;
      colorScheme?: string;
      locale?: {
        /** The site's WordPress locale narrowed to one we can generate in. */
        faker?: string;
        /** Display label for `faker`. */
        label?: string;
        wordpress?: string;
        /** Every generatable locale: code => label. Exactly what the REST API accepts. */
        allLocales?: Record<string, string>;
        /** Fallback locale, when none is chosen or one cannot be matched. */
        default?: string;
      };
      /**
       * Inlined by the server so the topbar knows its target on first paint.
       * Absent on an older build, in which case the provider fetches instead.
       */
      platforms?: PlatformState;
      /** What MCP would need on this site, and what it has. */
      mcp?: McpStatus;
    };
  }
}

/**
 * MCP's two dependencies, and whether the site has them.
 *
 * Separate booleans rather than one `available`, because "install the Abilities API" and
 * "install mcp-adapter" are different instructions and the user needs the right one.
 */
export interface McpStatus {
  available: boolean;
  abilities_api: boolean;
  adapter: boolean;
  /** How many generators there are — two tools each, at most. */
  abilities: number;
  /** How many tools are actually exposed under the current settings. */
  tools: number;
  /** Master switch: whether StoreSeeder serves an MCP server at all. */
  enabled: boolean;
  /** Whether the read-only preview tools are exposed. */
  preview: boolean;
  /** Whether the generate tools — the ones that write rows — are exposed. */
  generate: boolean;
  /** Whether the current user may change any of the three. */
  can_manage: boolean;
  /** The endpoint an MCP client connects to. */
  route: string;
}

/**
 * Whether one platform can generate one resource, and why not when it cannot.
 *
 * A bare boolean would be enough to dim a tile but not to explain it — and
 * "install WooCommerce Subscriptions" is actionable in a way that a greyed-out
 * card is not.
 */
export interface Capability {
  supported: boolean;
  reason: string;
  /** Plugin slug that would enable this, or '' when none applies. */
  extension: string;
  /**
   * Canonical fields this platform stores the resource *without*.
   *
   * Supported and incomplete is a real state — WooCommerce has customers but no separate
   * customer record, so `with_account` cannot mean what it means elsewhere. Naming them is what
   * lets the form say so instead of offering a control that does nothing.
   */
  ignored_fields: string[];
}

export interface PlatformInfo {
  id: string;
  label: string;
  active: boolean;
  version: string | null;
  /** Keyed by the canonical resource name, not by REST base. */
  supports: Record<string, Capability>;
  /**
   * Extra generation parameters only this platform understands, keyed by resource then by
   * parameter name. Merged into the form when this platform is the target, and left out
   * otherwise — another platform's field is a control that would be ignored.
   */
  fields: Record<string, Record<string, ParameterConfig>>;
}

export interface PlatformState {
  platforms: PlatformInfo[];
  /** The site-wide target, or '' for auto. */
  stored: string;
  /** What auto resolves to right now, or null when it cannot be decided. */
  resolved: string | null;
  /** True when more than one platform is active and none has been chosen. */
  ambiguous: boolean;
}

/**
 * A value one schema-driven field can hold.
 *
 * The set is closed by what `fieldsFromSchema` can produce: toggles give a
 * boolean, chips an array, ranges a `{ lo, hi }` pair, and the rest a string or a
 * number. Anything wider would put the burden of narrowing back on every control.
 */
export type ParamValue =
  | string
  | number
  | boolean
  | string[]
  | { lo: number; hi: number }
  | null
  | undefined;

export interface ParameterConfig {
  type: string;
  title?: string;
  description?: string;
  /**
   * Whatever the PHP schema declared. Unknown rather than any: it arrives as
   * JSON, so a caller wanting a number has to check it is one — see `asNumber`
   * in lib/fieldsFromSchema.ts.
   */
  default?: unknown;
  enum?: string[];
  minimum?: number;
  maximum?: number;
  items?: {
    type?: string;
    enum?: string[];
    default?: string[];
  };
  properties?: Record<string, ParameterConfig>;
  dependsOn?: Record<string, unknown>;
  format?: string;
  /**
   * Marks an id field as a foreign key into the target store, so the admin can offer a searchable
   * list instead of a number box. The value sent is still the id.
   */
  entity?: "product" | "customer";
}

export interface Generator {
  name: string;
  category: string;
  order: number;
  /** Key into the ICONS registry in lib/icons.tsx. */
  iconName: IconName;
  description: string;
  useCase?: string;
  /** REST base, e.g. `cart-sessions`. */
  route: string;
  /**
   * Canonical resource name, e.g. `cart_session`. Keys into the capability matrix.
   * Held separately rather than derived from `route` because the two key spaces
   * genuinely differ, and no singularisation rule survives `shipping_classes`.
   */
  resource: string;
  popular?: boolean;
  parameterConfig?: Record<string, ParameterConfig>;
}

export interface GeneratorResult {
  message: string;
  generated?: number;
  /** Generators are free to return extra fields; readers must narrow them. */
  [key: string]: unknown;
}

export interface StoredRun {
  count: number;
  timestamp: number;
  success: boolean;
  message: string;
}

export interface GlobalRun {
  route: string;
  count: number;
  timestamp: number;
  success: boolean;
  locale?: string;
  seed?: string;
}

export interface GeneratorPageParams extends Record<string, string | undefined> {
  type: string;
}

