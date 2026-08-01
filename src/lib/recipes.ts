import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";

import { generators } from "@/lib/generators";

/** One line of a recipe's plan, answered against the resolved target. */
export interface RecipeStep {
  /** Canonical resource name, e.g. `product_category`. */
  resource: string;
  /** How many to create at Medium. */
  count: number;
  /**
   * Whether the target can create this. `null` means no platform is resolved yet — "we cannot
   * say", which is not the same answer as a refusal.
   */
  supported: boolean | null;
  /** The driver's own words when it refuses. */
  reason: string;
  /** The plugin that would enable it, when one would. */
  extension: string;
  /** Canonical fields the platform stores the resource but not all of. */
  ignored_fields: string[];
}

export interface Recipe {
  id: string;
  name: string;
  description: string;
  /** Icon name from the shared registry. */
  icon: string;
  /** Accent token name — `green`, `violet`, … — never a colour. */
  accent: string;
  /** Locales this recipe ships vocabulary for. */
  locales: string[];
  plan: RecipeStep[];
  /**
   * The recipe's own mark, already filtered server-side, as a `data:` URI — or "" when the
   * archive ships none and the icon registry should answer instead.
   */
  icon_uri: string;
  /** False when a plugin registered this recipe rather than the downloaded archive shipping it. */
  bundled: boolean;
  /** Whether it ships vocabulary for the locale the run would use. */
  locale_shipped: boolean;
  /** What it falls back to when it does not. */
  fallback_locale: string;
  /** What the manifest claims that the files do not bear out. */
  issues: RecipeIssue[];
}

/** Whether a recipe would build a store that misrepresents itself. */
export function isBlocked(recipe: Recipe): boolean {
  return recipe.issues.some((i) => i.blocking);
}

/** Something the manifest claims that the files beside it do not bear out. */
export interface RecipeIssue {
  code: string;
  message: string;
  /** True only when the recipe would build a store that misrepresents itself. */
  blocking: boolean;
}

export interface RecipesResponse {
  platform: string;
  locale: string;
  /** Whether the archive has been fetched at all. */
  downloaded: boolean;
  /** Recipes the index promises that are not on disk — an interrupted download. */
  incomplete: string[];
  recipes: Recipe[];
}

/** How a size scales a recipe's declared counts. */
export interface RecipeSize {
  key: string;
  label: string;
  multiplier: number;
}

/**
 * The three sizes.
 *
 * A single fixed size cannot serve both a thirty-second smoke test and a perf test, and the point
 * of this page is that it asks nothing else — so the size is the one control, not the first field
 * of a form.
 */
export const SIZES: RecipeSize[] = [
  { key: "s", label: __("Small", "storeseeder"), multiplier: 0.25 },
  { key: "m", label: __("Medium", "storeseeder"), multiplier: 1 },
  { key: "l", label: __("Large", "storeseeder"), multiplier: 4 },
];

export const DEFAULT_SIZE = "m";

export function sizeFor(key: string): RecipeSize {
  return SIZES.find((s) => s.key === key) ?? SIZES[1];
}

/** A step's count at the chosen size. Never below one — a step that creates nothing is noise. */
export function scaledCount(step: RecipeStep, size: string): number {
  return Math.max(1, Math.round(step.count * sizeFor(size).multiplier));
}

/**
 * Only the steps the target will actually run.
 *
 * `supported === null` counts as runnable: with no platform resolved nothing runs anyway, and
 * hiding every step would make the card look empty rather than unconfigured.
 */
export function runnableSteps(recipe: Recipe): RecipeStep[] {
  return recipe.plan.filter((s) => false !== s.supported);
}

export function skippedSteps(recipe: Recipe): RecipeStep[] {
  return recipe.plan.filter((s) => false === s.supported);
}

export function totalRows(recipe: Recipe, size: string): number {
  return runnableSteps(recipe).reduce((t, s) => t + scaledCount(s, size), 0);
}

/**
 * The REST route that generates a resource.
 *
 * Read from the generator registry rather than derived: `cart_session` is served at
 * `cart-sessions` and `tax_class` at `tax_classes`, and no singularisation rule survives
 * `shipping_classes`. A recipe naming a resource with no registered generator is dropped, because
 * there is nowhere to send the request.
 */
export function routeFor(resource: string): string | null {
  return generators.find((g) => g.resource === resource)?.route ?? null;
}

export function labelFor(resource: string): string {
  return generators.find((g) => g.resource === resource)?.name ?? resource;
}

/** How many rows one POST may create. Mirrors the endpoint's own cap. */
export const CHUNK = 100;

/** One POST the runner will make. */
export interface RecipeCall {
  resource: string;
  route: string;
  count: number;
}

/**
 * Flatten a recipe into the calls that build it.
 *
 * Ordered, and the order is the recipe's: brands and categories before products, products and
 * customers before orders. An order needs its line items to point at something.
 *
 * Chunked at the endpoint's cap, which also buys honest progress — a 1,440-variation step is
 * fifteen calls, and a progress bar that moves fifteen times reads as working where one that sits
 * still for a minute reads as hung.
 */
export function planCalls(recipe: Recipe, size: string): RecipeCall[] {
  const calls: RecipeCall[] = [];

  for (const step of runnableSteps(recipe)) {
    const route = routeFor(step.resource);

    if (!route) continue;

    let left = scaledCount(step, size);

    while (left > 0) {
      const count = Math.min(CHUNK, left);
      calls.push({ resource: step.resource, route, count });
      left -= count;
    }
  }

  return calls;
}

/**
 * Re-key a resource-keyed tally by REST route.
 *
 * The runner counts by resource, because that is what a recipe's plan names, while the stats
 * store counts by route — and `cart_session` lives at `cart-sessions`. Converting here rather
 * than keeping two tallies means only one of them can be wrong.
 */
export function routeRows(rows: Record<string, number>): Record<string, number> {
  const byRoute: Record<string, number> = {};

  Object.entries(rows).forEach(([resource, n]) => {
    const route = routeFor(resource);

    if (route) byRoute[route] = (byRoute[route] ?? 0) + n;
  });

  return byRoute;
}

/**
 * A run id the ledger can group by.
 *
 * Minted here rather than server-side because a recipe is many requests and only the client knows
 * they belong together. Opaque to the server, which only stores it.
 */
export function mintRunId(recipeId: string): string {
  const stamp = Date.now().toString(36);
  const salt = Math.floor(Math.random() * 1e6).toString(36);

  return `rcp_${recipeId}_${stamp}${salt}`.replace(/[^a-z0-9_]/g, "").slice(0, 64);
}

export async function fetchRecipes(locale: string): Promise<RecipesResponse> {
  return apiFetch<RecipesResponse>({
    path: `/storeseeder/v1/recipes?locale=${encodeURIComponent(locale)}`,
  });
}

/**
 * Fetch the recipe archive.
 *
 * A POST rather than a parameter on the read: a GET that downloads eighty kilobytes from GitHub is
 * a GET a browser prefetcher will fire on its own.
 */
export async function syncRecipes(): Promise<{ recipes: number }> {
  return apiFetch<{ recipes: number }>({
    path: "/storeseeder/v1/recipes/sync",
    method: "POST",
  });
}
