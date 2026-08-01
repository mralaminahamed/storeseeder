import React, { useCallback, useEffect, useMemo, useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { __, _n, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import { AUTO } from "@/lib/platform";
import { getSettings } from "@/lib/settings";
import {
  DEFAULT_SIZE,
  SIZES,
  fetchRecipes,
  isBlocked,
  labelFor,
  mintRunId,
  planCalls,
  routeRows,
  runnableSteps,
  scaledCount,
  skippedSteps,
  syncRecipes,
  totalRows,
  type Recipe,
  type RecipeCall,
} from "@/lib/recipes";
import { Button } from "@/components/ui/button";
import { RecipeCard } from "@/components/recipes/RecipeCard";
import { usePlatform } from "@/providers/PlatformProvider";
import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";

/** What the runner has finished so far. */
interface RunProgress {
  /** Calls completed, of `total`. */
  done: number;
  total: number;
  /** The resource being written right now. */
  resource: string;
  /** Rows written per resource. */
  rows: Record<string, number>;
  /** One message per resource that failed, not one per chunk. */
  errors: string[];
}

type Stage = "pick" | "running" | "done";

/**
 * Build a whole shop in one click.
 *
 * The generator pages answer "make me two hundred products". This answers "make me a shop", which
 * is a different question: a coherent store is nine resources in dependency order sharing one
 * vocabulary, and assembling that by hand means nine visits and a lot of guessing about what a
 * grocer's categories should be called.
 */
export default function RecipesPage() {
  const { target } = usePlatform();
  const { counts, recordRun, discardRun } = useStats();
  const { toast } = useToast();

  const locale =
    getSettings().defaultLocale ?? window.storeseederApi?.locale?.faker ?? "en_US";

  const [recipes, setRecipes] = useState<Recipe[]>([]);
  const [downloaded, setDownloaded] = useState(true);
  const [incomplete, setIncomplete] = useState<string[]>([]);
  const [syncing, setSyncing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [picked, setPicked] = useState<string | null>(null);
  const [size, setSize] = useState(DEFAULT_SIZE);
  const [stage, setStage] = useState<Stage>("pick");
  const [progress, setProgress] = useState<RunProgress | null>(null);
  const [runId, setRunId] = useState("");
  const [undoing, setUndoing] = useState(false);
  const [undone, setUndone] = useState(false);

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

  const recipe = useMemo(
    () => recipes.find((r) => r.id === picked) ?? null,
    [recipes, picked],
  );

  const generatedRows = useMemo(
    () => Object.values(counts).reduce((t, n) => t + n, 0),
    [counts],
  );

  const run = useCallback(async () => {
    if (!recipe) return;

    const calls: RecipeCall[] = planCalls(recipe, size);

    if (0 === calls.length) return;

    const id = mintRunId(recipe.id);
    const rows: Record<string, number> = {};
    const errors: string[] = [];
    const failedResources = new Set<string>();

    setRunId(id);
    setUndone(false);
    setStage("running");
    setProgress({ done: 0, total: calls.length, resource: calls[0].resource, rows, errors });

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
            recipe_run: id,
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

        recordRun(
          call.route,
          call.count,
          false,
          err instanceof Error ? err.message : "",
          { locale },
        );
      }

      // Progress is the real count of completed calls, not an animation against a guessed
      // duration. A recipe takes a minute; a bar that fills in two seconds and then waits reads
      // as a hang.
      setProgress({
        done: i + 1,
        total: calls.length,
        resource: calls[Math.min(i + 1, calls.length - 1)].resource,
        rows: { ...rows },
        errors: [...errors],
      });
    }

    setStage("done");
  }, [recipe, size, locale, target, recordRun]);

  const sync = useCallback(async () => {
    if (syncing) return;

    setSyncing(true);

    try {
      await syncRecipes();

      const data = await fetchRecipes(locale);

      setRecipes(data.recipes ?? []);
      setDownloaded(Boolean(data.downloaded));
      setIncomplete(data.incomplete ?? []);
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
    if (!runId || undoing) return;

    setUndoing(true);

    try {
      let guard = 0;

      // Purge deletes in batches. Loop until it reports nothing left, with a ceiling so a writer
      // that refuses every row cannot spin forever.
      for (;;) {
        const result = await apiFetch<{ remaining: number }>({
          path: "/storeseeder/v1/generated",
          method: "DELETE",
          data: { run_id: runId, limit: 200 },
        });

        guard += 1;

        if (result.remaining <= 0 || guard > 200) break;
      }

      setUndone(true);
      discardRun(routeRows(progress?.rows ?? {}));
      toast(__("Recipe undone", "storeseeder"), __("Every row it created is gone.", "storeseeder"));
    } catch (err) {
      toast(
        __("Could not undo the recipe", "storeseeder"),
        err instanceof Error ? err.message : "",
      );
    } finally {
      setUndoing(false);
    }
  }, [runId, undoing, progress, discardRun, toast]);

  if (loading) {
    return (
      <div className="fp-recipes-note">{__("Loading recipes…", "storeseeder")}</div>
    );
  }

  if (failed) {
    return (
      <div className="fp-recipes-note">
        {__("Could not load the recipes. Reload the page to try again.", "storeseeder")}
      </div>
    );
  }

  // ── running ────────────────────────────────────────────────────────────────
  if ("running" === stage && recipe && progress) {
    const pct = progress.total > 0 ? (progress.done / progress.total) * 100 : 0;
    const steps = runnableSteps(recipe);

    return (
      <div className="fp-card fp-recipe-run">
        <div className="fp-recipe-run-head">
          <div>
            <div className="fp-recipe-run-title">
              {sprintf(
                /* translators: %s: recipe name, e.g. Corner grocer. */
                __("Building %s", "storeseeder"),
                recipe.name,
              )}
            </div>
            <div className="fp-recipe-run-sub">
              {__(
                "Resources run in order — an order needs its products and customers to exist first.",
                "storeseeder",
              )}
            </div>
          </div>
          <div className="fp-recipe-run-step mono">
            {sprintf(
              /* translators: 1: calls completed, 2: total calls. */
              __("%1$s / %2$s", "storeseeder"),
              String(progress.done),
              String(progress.total),
            )}
          </div>
        </div>

        <div className="fp-progress-track">
          <div className="fp-progress-fill" style={{ width: `${pct}%` }} />
        </div>

        <div className="fp-recipe-steps">
          {steps.map((step) => {
            const written = progress.rows[step.resource] ?? 0;
            const wanted = scaledCount(step, size);
            const active = step.resource === progress.resource && written < wanted;
            const done = written >= wanted;

            return (
              <div
                key={step.resource}
                className={`fp-recipe-step${done ? " done" : ""}${active ? " on" : ""}`}
              >
                <span className="fp-recipe-step-dot">
                  {done && <Icon name="check" size={11} />}
                </span>
                <span>{labelFor(step.resource)}</span>
                <span className="fp-recipe-step-n mono">
                  {written.toLocaleString()} / {wanted.toLocaleString()}
                </span>
              </div>
            );
          })}

          {/*
            Shown, not hidden. A resource the driver refuses was struck through on the card before
            the click, and dropping it from the run silently would make the step list disagree with
            the card the user just read. It never enters the loop.
          */}
          {skippedSteps(recipe).map((step) => (
            <div key={step.resource} className="fp-recipe-step skip">
              <span className="fp-recipe-step-dot" />
              <span>
                {sprintf(
                  /* translators: %s: resource name, e.g. Transactions. */
                  __("%s — not on this platform", "storeseeder"),
                  labelFor(step.resource),
                )}
              </span>
              <span className="fp-recipe-step-n mono">0</span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  // ── done ───────────────────────────────────────────────────────────────────
  if ("done" === stage && recipe && progress) {
    const written = Object.values(progress.rows).reduce((t, n) => t + n, 0);

    return (
      <div className="fp-card fp-recipe-done">
        <div className="fp-recipe-done-head">
          <span className={`fp-recipe-done-ic${undone ? " undone" : ""}`}>
            <Icon name={undone ? "refresh" : "check"} size={21} />
          </span>
          <div>
            <div className="fp-recipe-run-title">
              {undone
                ? __("Recipe undone", "storeseeder")
                : sprintf(
                    /* translators: %s: recipe name. */
                    __("%s built", "storeseeder"),
                    recipe.name,
                  )}
            </div>
            <div className="fp-recipe-run-sub">
              {undone
                ? __("Every row it created has been removed.", "storeseeder")
                : sprintf(
                    /* translators: 1: row count, 2: resource count. */
                    _n(
                      "%1$s row across %2$s resource.",
                      "%1$s rows across %2$s resources.",
                      Object.keys(progress.rows).length,
                      "storeseeder",
                    ),
                    written.toLocaleString(),
                    String(Object.keys(progress.rows).length),
                  )}
            </div>
          </div>
        </div>

        {progress.errors.length > 0 && !undone && (
          <div className="fp-recipe-guard">
            <Icon name="alert" size={14} />
            <span>{progress.errors.join(" · ")}</span>
          </div>
        )}

        {!undone && (
          <div className="fp-recipe-tally">
            {Object.entries(progress.rows).map(([resource, n]) => (
              <div key={resource}>
                <div className="fp-recipe-tally-k">{labelFor(resource)}</div>
                <div className="fp-recipe-tally-v mono">{n.toLocaleString()}</div>
              </div>
            ))}
          </div>
        )}

        <div className="fp-recipe-done-foot">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setStage("pick");
              setPicked(null);
            }}
          >
            {__("Build another", "storeseeder")}
          </Button>
          <span className="fp-recipe-spacer" />
          <span className="fp-recipe-runid mono">{runId}</span>
          {!undone && (
            <Button
              variant="outline"
              size="sm"
              disabled={undoing}
              onClick={() => void undo()}
            >
              <Icon name="refresh" size={13} />
              {undoing
                ? __("Undoing…", "storeseeder")
                : __("Undo this recipe", "storeseeder")}
            </Button>
          )}
        </div>
      </div>
    );
  }

  // ── pick ───────────────────────────────────────────────────────────────────
  /**
   * The archive has three states and they are not degrees of the same thing: never fetched (has a
   * button), fetched but torn (has a different button), and fetched and fine.
   */
  function body() {
    if (!downloaded) {
      return (
        <div className="fp-recipes-empty">
          <span className="fp-recipes-empty-ic">
            <Icon name="store" size={22} />
          </span>
          <div className="fp-recipes-empty-title">
            {__("Recipes are not downloaded yet", "storeseeder")}
          </div>
          <p className="fp-recipes-empty-body">
            {__(
              "Recipes live in a separate repository so a new shop type does not need a plugin update. StoreSeeder fetches about 90 KB from github.com once, then works offline.",
              "storeseeder",
            )}
          </p>
          <Button disabled={syncing} onClick={() => void sync()}>
            <Icon name="refresh" size={14} />
            {syncing
              ? __("Downloading…", "storeseeder")
              : __("Download the recipes", "storeseeder")}
          </Button>
        </div>
      );
    }

    if (0 === recipes.length) {
      return (
        <div className="fp-recipes-note">
          {__("No recipes are registered.", "storeseeder")}
        </div>
      );
    }

    return (
      <>
        {incomplete.length > 0 && (
          <div className="fp-recipe-guard standalone">
            <Icon name="alert" size={14} />
            <span>
              {sprintf(
                /* translators: %s: comma-separated recipe ids. */
                __(
                  "The archive lists %s but they did not arrive — the download was interrupted. Sync again.",
                  "storeseeder",
                ),
                incomplete.join(", "),
              )}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={syncing}
              onClick={() => void sync()}
            >
              {syncing ? __("Syncing…", "storeseeder") : __("Sync", "storeseeder")}
            </Button>
          </div>
        )}
        <div className="fp-recipe-grid">
          {recipes.map((r) => (
            <RecipeCard
              key={r.id}
              recipe={r}
              size={size}
              selected={r.id === picked}
              onSelect={setPicked}
            />
          ))}
        </div>
      </>
    );
  }

  return (
    <>
      <div className="fp-page-head">
        <div>
          <div className="fp-page-title">{__("Recipes", "storeseeder")}</div>
          <div className="fp-page-sub">
            {__(
              "A recipe is a vocabulary — names, categories, brands, price bands — that makes every generator produce one coherent shop. Pick one and it fills the store in dependency order.",
              "storeseeder",
            )}
          </div>
        </div>
      </div>

      {body()}

      {!recipe ? (
        <div className="fp-recipe-bar idle">
          {__("Pick a recipe to continue.", "storeseeder")}
        </div>
      ) : (
        <div className="fp-recipe-bar">
          <div className="fp-recipe-sizes">
            <span className="fp-recipe-sizes-label">{__("Size", "storeseeder")}</span>
            <div className="fp-seg">
              {SIZES.map((s) => (
                <button
                  key={s.key}
                  type="button"
                  className="fp-focusable"
                  aria-pressed={size === s.key}
                  onClick={() => setSize(s.key)}
                >
                  {s.label}
                </button>
              ))}
            </div>
          </div>

          <div className="fp-recipe-summary">
            {sprintf(
              /* translators: 1: row count, 2: resource count, 3: recipe name. */
              __("%1$s rows across %2$s resources · %3$s", "storeseeder"),
              totalRows(recipe, size).toLocaleString(),
              String(runnableSteps(recipe).length),
              recipe.name,
            )}
          </div>

          <div className="fp-recipe-actions">
            <Button variant="outline" onClick={() => setPicked(null)}>
              {__("Clear", "storeseeder")}
            </Button>
            <Button
              data-testid="recipe-run"
              disabled={isBlocked(recipe)}
              onClick={() => void run()}
            >
              <Icon name="play" size={14} />
              {__("Create the store", "storeseeder")}
            </Button>
          </div>

          {/*
            Building onto a store that already holds generated rows gives two shops interleaved.
            Warned rather than blocked: it is a legitimate thing to want, and this page's job is to
            make sure nobody does it by accident.
          */}
          {generatedRows > 0 && (
            <div className="fp-recipe-guard">
              <Icon name="alert" size={14} />
              <span>
                {sprintf(
                  /* translators: %s: number of rows already generated. */
                  __(
                    "This store already holds %s generated rows. Building on top of them leaves two shops interleaved — purge first, or continue and both will exist.",
                    "storeseeder",
                  ),
                  generatedRows.toLocaleString(),
                )}
              </span>
            </div>
          )}
        </div>
      )}
    </>
  );
}
