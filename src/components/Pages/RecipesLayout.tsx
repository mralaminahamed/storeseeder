import React, { useCallback, useEffect, useMemo, useState } from "@wordpress/element";
import { Outlet, useNavigate } from "react-router-dom";
import apiFetch from "@wordpress/api-fetch";
import { __, sprintf } from "@wordpress/i18n";

import { AUTO } from "@/lib/platform";
import { getSettings } from "@/lib/settings";
import {
  DEFAULT_SIZE,
  fetchRecipes,
  labelFor,
  mintRunId,
  planCalls,
  routeRows,
  syncRecipes,
  type Recipe,
  type RecipeCall,
} from "@/lib/recipes";
import { PageHead } from "@/components/ui/PageHead";
import { RecipeSkeleton } from "@/components/recipes/RecipeSkeleton";
import type { RecipesContext, RunProgress } from "@/components/recipes/context";
import { usePlatform } from "@/providers/PlatformProvider";
import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";

/**
 * The page's own description.
 *
 * A constant because the loading state shows it too — it is known before any request, so
 * withholding it would make the header grow a line when the data lands. Two copies of the sentence
 * is how the two states drift apart.
 */
export const DESCRIPTION = __(
  "A recipe is a vocabulary — names, categories, brands, price bands — that makes every generator produce one coherent shop. Pick one and it fills the store in dependency order.",
  "storeseeder",
);

/**
 * Everything the three recipe screens share, and the one thing none of them can own.
 *
 * Picking, building and the result are three screens rather than three values of a `stage`
 * variable, because they are three different things to be looking at and each deserves an address:
 * a build you can link someone to, a Back button that returns to the list rather than out of the
 * plugin, and a refresh that does not silently drop you somewhere else.
 *
 * The run loop lives here and not in the screen that starts it. Navigating from `run` to `done`
 * unmounts one route and mounts another; a loop owned by either would be cancelled a third of the
 * way through a minute of sequential requests. The layout stays mounted across all three, so the
 * run outlives the screen — and someone can leave the page entirely and come back to it still
 * going.
 */
export default function RecipesLayout() {
  const navigate = useNavigate();
  const { target } = usePlatform();
  const { counts, recordRun, discardRun } = useStats();
  const { toast } = useToast();

  const locale =
    getSettings().defaultLocale ?? window.storeseederApi?.locale?.faker ?? "en_US";
  const homeUrl = window.storeseederApi?.homeUrl ?? "";

  const [recipes, setRecipes] = useState<Recipe[]>([]);
  const [downloaded, setDownloaded] = useState(true);
  const [resolved, setResolved] = useState(true);
  const [incomplete, setIncomplete] = useState<string[]>([]);
  const [adminUrls, setAdminUrls] = useState<Record<string, string>>({});
  const [syncing, setSyncing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [size, setSize] = useState(DEFAULT_SIZE);
  const [progress, setProgress] = useState<RunProgress | null>(null);
  const [undoing, setUndoing] = useState(false);

  // Refetched when the target changes: which resources a store refuses is a property of the
  // platform, and a stale answer would promise rows the driver will not write.
  useEffect(() => {
    let cancelled = false;

    setLoading(true);
    setFailed(false);

    void (async () => {
      try {
        const data = await fetchRecipes(locale);

        if (cancelled) return;

        setRecipes(data.recipes ?? []);
        setDownloaded(Boolean(data.downloaded));
        setIncomplete(data.incomplete ?? []);
        setAdminUrls(data.adminUrls ?? {});
        // '' means Auto could not decide — more than one store is active and none was chosen.
        setResolved("" !== data.platform);
      } catch {
        if (!cancelled) setFailed(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [locale, target]);

  const generatedRows = useMemo(
    () => Object.values(counts).reduce((t, n) => t + n, 0),
    [counts],
  );

  const run = useCallback(
    async (recipe: Recipe) => {
      const calls: RecipeCall[] = planCalls(recipe, size);

      if (0 === calls.length) return;

      const runId = mintRunId(recipe.id);
      const rows: Record<string, number> = {};
      const errors: string[] = [];
      const failedResources = new Set<string>();

      setProgress({
        recipeId: recipe.id,
        done: 0,
        total: calls.length,
        resource: calls[0].resource,
        rows,
        errors,
        runId,
        undone: false,
      });

      void navigate(`/recipes/${recipe.id}/run`);

      for (let i = 0; i < calls.length; i++) {
        const call = calls[i];

        try {
          await apiFetch({
            path: `/storeseeder/v1/${call.route}/generate`,
            method: "POST",
            data: {
              count: call.count,
              locale,
              include_meta: false,
              recipe: recipe.id,
              recipe_run: runId,
              // Always explicit. Letting the server fall back to auto would mean half a shop
              // could land somewhere other than the store named in the topbar.
              platform: target ?? AUTO,
            },
          });

          rows[call.resource] = (rows[call.resource] ?? 0) + call.count;
          recordRun(call.route, call.count, true, "", { locale });
        } catch (err) {
          // One message per resource, not per chunk: fifteen identical failures for one broken
          // step is not fifteen times as informative.
          if (!failedResources.has(call.resource)) {
            failedResources.add(call.resource);
            errors.push(
              sprintf(
                /* translators: 1: resource name, 2: error message. */
                __("%1$s: %2$s", "storeseeder"),
                labelFor(call.resource),
                err instanceof Error ? err.message : __("failed.", "storeseeder"),
              ),
            );
          }

          recordRun(call.route, call.count, false, err instanceof Error ? err.message : "", {
            locale,
          });
        }

        // Progress is the real count of completed calls, not an animation against a guessed
        // duration. A recipe takes a minute; a bar that fills in two seconds and then waits reads
        // as a hang.
        setProgress({
          recipeId: recipe.id,
          done: i + 1,
          total: calls.length,
          resource: calls[Math.min(i + 1, calls.length - 1)].resource,
          rows: { ...rows },
          errors: [...errors],
          runId,
          undone: false,
        });
      }

      // Replaced, not pushed: Back from the result should return to the list, not into a run that
      // has already finished and would immediately bounce forward again.
      void navigate(`/recipes/${recipe.id}/done`, { replace: true });
    },
    [size, locale, target, recordRun, navigate],
  );

  const sync = useCallback(async () => {
    if (syncing) return;

    setSyncing(true);

    try {
      await syncRecipes();

      const data = await fetchRecipes(locale);

      setRecipes(data.recipes ?? []);
      setDownloaded(Boolean(data.downloaded));
      setIncomplete(data.incomplete ?? []);
      setAdminUrls(data.adminUrls ?? {});
      setResolved("" !== data.platform);
      toast(
        __("Recipes ready", "storeseeder"),
        sprintf(
          /* translators: %s: number of recipes. */
          __("%s available.", "storeseeder"),
          String((data.recipes ?? []).length),
        ),
      );
    } catch (err) {
      toast(
        __("Could not download the recipes", "storeseeder"),
        err instanceof Error ? err.message : "",
      );
    } finally {
      setSyncing(false);
    }
  }, [syncing, locale, toast]);

  const undo = useCallback(async () => {
    if (!progress?.runId || undoing) return;

    setUndoing(true);

    try {
      let guard = 0;

      // Purge deletes in batches. Loop until it reports nothing left, with a ceiling so a writer
      // that refuses every row cannot spin forever.
      for (;;) {
        const result = await apiFetch<{ remaining: number }>({
          path: "/storeseeder/v1/generated",
          method: "DELETE",
          data: { run_id: progress.runId, limit: 200 },
        });

        guard += 1;

        if (result.remaining <= 0 || guard > 200) break;
      }

      discardRun(routeRows(progress.rows));
      setProgress((p) => (p ? { ...p, undone: true } : p));
      toast(
        __("Recipe undone", "storeseeder"),
        __("Every row it created is gone.", "storeseeder"),
      );
    } catch (err) {
      toast(
        __("Could not undo the recipe", "storeseeder"),
        err instanceof Error ? err.message : "",
      );
    } finally {
      setUndoing(false);
    }
  }, [progress, undoing, discardRun, toast]);

  // A skeleton, not a spinner: this wait has a known shape, so standing in for it means the
  // layout does not jump when the cards arrive.
  if (loading) {
    return (
      <div className="fp-recipes-page fp-enter">
        <PageHead title={__("Recipes", "storeseeder")} description={DESCRIPTION} />
        <RecipeSkeleton />
      </div>
    );
  }

  if (failed) {
    return (
      <div className="fp-recipes-page fp-enter fp-recipes-note">
        {__("Could not load the recipes. Reload the page to try again.", "storeseeder")}
      </div>
    );
  }

  const context: RecipesContext = {
    recipes,
    downloaded,
    incomplete,
    resolved,
    adminUrls,
    syncing,
    sync,
    size,
    setSize,
    progress,
    run,
    undoing,
    undo,
    generatedRows,
    homeUrl,
  };

  return (
    <div className="fp-recipes-page fp-enter">
      <Outlet context={context} />
    </div>
  );
}
