import React, { createInterpolateElement, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import {
  SIZES,
  isBlocked,
  runnableSteps,
  totalRows,
  type Recipe,
} from "@/lib/recipes";
import { Button } from "@/components/ui/button";
import { PageHead } from "@/components/ui/PageHead";
import { RecipeCard } from "@/components/recipes/RecipeCard";
import { useRecipes } from "@/components/recipes/context";
import { DESCRIPTION } from "@/components/Pages/RecipesLayout";

/**
 * Why "Create the store" is disabled, or undefined when it is not.
 *
 * Why, not just that. Both reasons are fixable — choose a target, sync the recipes — and a greyed
 * button with no explanation is a dead end where an instruction belongs.
 */
function blockedReason(recipe: Recipe, resolved: boolean): string | undefined {
  if (!resolved) {
    return __("Choose a target platform in the topbar first.", "storeseeder");
  }

  return recipe.issues.find((i) => i.blocking)?.message;
}

/** Pick a recipe and a size. The index of `/recipes`. */
export default function RecipePicker() {
  const {
    recipes,
    downloaded,
    incomplete,
    resolved,
    syncing,
    sync,
    size,
    setSize,
    run,
    generatedRows,
  } = useRecipes();

  // Local to this screen: a selection is not a run, and nothing downstream needs to know about
  // one. It resets on the way back from a build, which is what "Build another" should mean.
  const [picked, setPicked] = useState<string | null>(null);

  const recipe = recipes.find((r) => r.id === picked) ?? null;

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
            <Button variant="outline" size="sm" disabled={syncing} onClick={() => void sync()}>
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
      <PageHead title={__("Recipes", "storeseeder")} description={DESCRIPTION} />

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

          {/*
            The row count and the recipe name are what a second glance is looking for, so they are
            the two things set in the text colour while the rest stays quiet. `createInterpolateElement`
            rather than concatenation: a translator has to be able to move the emphasis, and in
            several languages the order changes.
          */}
          <div className="fp-recipe-summary">
            {createInterpolateElement(
              sprintf(
                /* translators: 1: row count, 2: resource count, 3: recipe name. */
                __("<b>%1$s</b> rows across %2$s resources · <n>%3$s</n>", "storeseeder"),
                totalRows(recipe, size).toLocaleString(),
                String(runnableSteps(recipe).length),
                recipe.name,
              ),
              { b: <strong />, n: <strong /> },
            )}
          </div>

          <div className="fp-recipe-actions">
            <Button variant="outline" onClick={() => setPicked(null)}>
              {__("Clear", "storeseeder")}
            </Button>
            <Button
              data-testid="recipe-run"
              disabled={isBlocked(recipe) || !resolved}
              title={blockedReason(recipe, resolved)}
              onClick={() => void run(recipe)}
            >
              <Icon name="play" size={14} />
              {__("Create the store", "storeseeder")}
            </Button>
          </div>

          {/*
            With more than one store active and none chosen, `Auto` is deliberately ambiguous —
            guessing which one to write to is the failure nobody notices afterwards. Said here
            rather than discovered as nine consecutive 409s after the click.
          */}
          {!resolved && (
            <div className="fp-recipe-guard">
              <Icon name="alert" size={14} />
              <span>
                {__(
                  "More than one store is active and no target is chosen, so StoreSeeder cannot tell where these rows should go. Pick one in the topbar first.",
                  "storeseeder",
                )}
              </span>
            </div>
          )}

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
