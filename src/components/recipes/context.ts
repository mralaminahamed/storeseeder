import { useOutletContext } from "react-router-dom";

import type { Recipe } from "@/lib/recipes";

/** What the runner has finished so far. */
export interface RunProgress {
  /** The recipe being built. Kept here so a refreshed `/recipes/x/run` can tell it is stale. */
  recipeId: string;
  /** Calls completed, of `total`. */
  done: number;
  total: number;
  /** The resource being written right now. */
  resource: string;
  /** Rows written per resource. */
  rows: Record<string, number>;
  /** One message per resource that failed, not one per chunk. */
  errors: string[];
  /** The ledger id every row of this run carries. */
  runId: string;
  /** Whether the whole run has since been undone. */
  undone: boolean;
}

/**
 * Everything the three recipe screens share.
 *
 * It lives on the layout rather than in each screen because a run is a minute of sequential
 * requests and the screens are routes: navigating from `run` to `done` unmounts one and mounts the
 * other, and a loop owned by either would be cancelled halfway through. The layout stays mounted
 * across all three, so the run outlives the screen that started it — which also means someone can
 * leave the page and come back to it still going.
 */
export interface RecipesContext {
  recipes: Recipe[];
  /** Whether the archive has been fetched at all. */
  downloaded: boolean;
  /** Recipes the index promises that are not on disk — an interrupted download. */
  incomplete: string[];
  /** False when more than one store is active and none was chosen. */
  resolved: boolean;
  /** Where each resource lives in wp-admin on the resolved target, so a result can link to it. */
  adminUrls: Record<string, string>;
  syncing: boolean;
  /**
   * Fetch the archive. `force` deletes the local copy first rather than writing over it, which is what
   * Refresh needs: an overwrite keeps any file a newer archive dropped.
   */
  sync: (force?: boolean) => Promise<void>;

  /** The chosen size, shared so the run screen can label its steps with the same counts. */
  size: string;
  setSize: (size: string) => void;

  progress: RunProgress | null;
  /** Start a run. Resolves when every call has been made. */
  run: (recipe: Recipe) => Promise<void>;
  undoing: boolean;
  undo: () => Promise<void>;

  /** How many rows the store already holds, for the re-run guard. */
  generatedRows: number;
  /** The site's front page, or "" when none was inlined. */
  homeUrl: string;
}

export function useRecipes(): RecipesContext {
  return useOutletContext<RecipesContext>();
}
