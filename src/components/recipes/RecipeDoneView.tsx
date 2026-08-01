import React, { useState } from "@wordpress/element";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { __, _n, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import { labelFor } from "@/lib/recipes";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/overlays/ConfirmDialog";
import { useRecipes } from "@/components/recipes/context";

/** What a run produced. `/recipes/:id/done`. */
export default function RecipeDoneView() {
  const { id = "" } = useParams();
  const navigate = useNavigate();
  const { recipes, progress, undoing, undo, homeUrl, adminUrls } = useRecipes();

  const [confirming, setConfirming] = useState(false);

  const recipe = recipes.find((r) => r.id === id) ?? null;

  // The tally lives in memory with the run, so a reload has nothing to show. Back to the list
  // rather than an empty result panel claiming a build that this page no longer knows about.
  if (!recipe || !progress || progress.recipeId !== id) {
    return <Navigate to="/recipes" replace />;
  }

  const { undone, runId } = progress;
  const written = Object.values(progress.rows).reduce((t, n) => t + n, 0);
  const resources = Object.keys(progress.rows).length;

  // ── undone ─────────────────────────────────────────────────────────────────
  // Its own small panel rather than the built one with four things hidden. Nothing was created, so
  // a tally of what was created, a link to look at it and a button to remove it are all answers to
  // questions that no longer exist — and a card that is mostly gaps reads as broken rather than as
  // finished.
  if (undone) {
    return (
      <div className="fp-card fp-recipe-undone">
        <span className="fp-recipe-done-ic undone">
          <Icon name="undo" size={21} stroke={2.2} />
        </span>
        <div className="fp-recipe-run-title">{__("Recipe undone", "storeseeder")}</div>
        <p className="fp-recipe-run-sub">
          {sprintf(
            /* translators: %s: recipe name. */
            __(
              "Every row %s created has been removed. The store is back where it started.",
              "storeseeder",
            ),
            recipe.name,
          )}
        </p>
        <Button
          variant="primary"
          size="lg"
          type="button"
          onClick={() => void navigate("/recipes")}
        >
          {__("Build another", "storeseeder")}
        </Button>
      </div>
    );
  }

  // ── built ──────────────────────────────────────────────────────────────────
  return (
    <div className="fp-card fp-recipe-done">
      <div className="fp-recipe-done-head">
        <span className="fp-recipe-done-ic">
          <Icon name="check" size={21} stroke={2.2} />
        </span>
        <div>
          <div className="fp-recipe-run-title">
            {sprintf(
              /* translators: %s: recipe name. */
              __("%s built", "storeseeder"),
              recipe.name,
            )}
          </div>
          <div className="fp-recipe-run-sub">
            {sprintf(
              /* translators: 1: row count, 2: resource count. */
              _n(
                "%1$s row across %2$s resource.",
                "%1$s rows across %2$s resources.",
                resources,
                "storeseeder",
              ),
              written.toLocaleString(),
              String(resources),
            )}
          </div>
        </div>
      </div>

      {/*
        A list, not a joined string. Three failed resources ran together behind a middle dot into
        one unreadable line, which is the shape that gets skipped.
      */}
      {progress.errors.length > 0 && (
        <div className="fp-recipe-guard">
          <Icon name="alert" size={14} />
          <ul className="fp-recipe-errors">
            {progress.errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      )}

      {/*
        The counts are the navigation. Someone who has just been told there are 180 new products
        wants to look at them, and eight numbers that go nowhere is a receipt where a door belongs.
        A resource whose driver has no screen for it stays plain text rather than becoming a link
        that lands somewhere unrelated.
      */}
      <div className="fp-recipe-tally">
        {Object.entries(progress.rows).map(([resource, n]) => {
          const label = labelFor(resource);
          const href = adminUrls[resource];
          const count = <span className="fp-recipe-tally-v mono">{n.toLocaleString()}</span>;

          if (!href) {
            return (
              <div key={resource}>
                <div className="fp-recipe-tally-k">{label}</div>
                {count}
              </div>
            );
          }

          return (
            <a
              key={resource}
              className="fp-recipe-tally-link fp-focusable"
              href={href}
              title={sprintf(
                /* translators: %s: resource name, e.g. Products. */
                __("Open %s", "storeseeder"),
                label,
              )}
            >
              <span className="fp-recipe-tally-k">
                {label}
                <Icon name="external" size={11} />
              </span>
              {count}
            </a>
          );
        })}
      </div>

      <div className="fp-recipe-done-foot">
        {/*
          The front page rather than a per-driver storefront route: every platform puts its shop
          somewhere different, and a link that guesses wrong is worse than one that lands
          somewhere true. Hidden entirely when the site did not inline a URL.
        */}
        {homeUrl && (
          <Button
            variant="outline"
            size="sm"
            icon="external"
            type="button"
            onClick={() => window.open(homeUrl, "_blank", "noopener,noreferrer")}
          >
            {__("View the store", "storeseeder")}
          </Button>
        )}
        <Button
          variant="outline"
          size="sm"
          type="button"
          onClick={() => void navigate("/recipes")}
        >
          {__("Build another", "storeseeder")}
        </Button>

        <span className="fp-recipe-spacer" />

        {/*
          Danger, and alone on its side of the bar. It is the only control here that removes
          anything, and it was the same outline button as the two that do not.

          The run id rides on it rather than standing as its own column between the actions. It is
          a handle for `wp storeseeder cleanup --run_id=`, useful to roughly nobody in the moment,
          and it previously had equal weight with the buttons.
        */}
        <Button
          variant="danger"
          size="sm"
          icon="undo"
          type="button"
          disabled={undoing}
          title={sprintf(
            /* translators: %s: the run identifier, e.g. rcp_grocery_ab12cd. */
            __(
              "Removes only what this run created. From the command line: wp storeseeder cleanup --run_id=%s",
              "storeseeder",
            ),
            runId,
          )}
          onClick={() => setConfirming(true)}
        >
          {undoing ? __("Undoing…", "storeseeder") : __("Undo this recipe", "storeseeder")}
        </Button>
      </div>

      {/*
        Asked, because this is thousands of rows across nine resources and the button sits two
        pixels from "Build another". The count is in the question rather than the abstract: "1,794
        rows" is a thing someone can weigh, where "this recipe" is not.
      */}
      {confirming && (
        <ConfirmDialog
          testId="confirm-undo-recipe"
          icon="undo"
          title={__("Undo this recipe?", "storeseeder")}
          body={
            <>
              <p>
                {sprintf(
                  /* translators: 1: row count, 2: resource count, 3: recipe name. */
                  __(
                    "This permanently removes the %1$s rows across %2$s resources that %3$s created.",
                    "storeseeder",
                  ),
                  written.toLocaleString(),
                  String(resources),
                  recipe.name,
                )}
              </p>
              <p>
                {__(
                  "Nothing else is touched — only rows this run recorded creating are removed, and anything you or another run created stays.",
                  "storeseeder",
                )}
              </p>
            </>
          }
          confirmLabel={sprintf(
            /* translators: %s: number of rows. */
            __("Yes, remove %s rows", "storeseeder"),
            written.toLocaleString(),
          )}
          busyLabel={__("Removing…", "storeseeder")}
          busy={undoing}
          onCancel={() => setConfirming(false)}
          onConfirm={() => {
            void undo().then(() => setConfirming(false));
          }}
        />
      )}
    </div>
  );
}
