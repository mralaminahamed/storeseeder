import type { IconName } from "@/admin/lib/icons";

declare global {
  interface Window {
    storeseederApi?: {
      restUrl?: string;
      restNonce?: string;
      version?: string;
      ajaxUrl?: string;
      adminColors?: Record<string, string>;
      colorScheme?: string;
      locale?: {
        faker?: string;
        label?: string;
        wordpress?: string;
        allLocales?: Record<string, string>;
      };
    };
  }
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
}

export interface Generator {
  name: string;
  category: string;
  order: number;
  /** Key into the ICONS registry in lib/icons.tsx. */
  iconName: IconName;
  description: string;
  useCase?: string;
  route: string;
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

