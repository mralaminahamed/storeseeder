import React from "@wordpress/element";
import { Navigate, useParams } from "react-router-dom";
import { __, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import { labelFor, runnableSteps, scaledCount, skippedSteps } from "@/lib/recipes";
import { useRecipes } from "@/components/recipes/context";

/** A recipe being built. `/recipes/:id/run`. */
export default function RecipeRunView() {
  const { id = "" } = useParams();
  const { recipes, progress, size } = useRecipes();

  const recipe = recipes.find((r) => r.id === id) ?? null;

  // A run lives in memory, so this address survives a reload but the run does not. Rather than
  // render an empty progress bar for a build that is not happening, hand the visitor back to the
  // list — `replace`, so Back does not bounce them straight into the same dead end.
  if (!recipe || !progress || progress.recipeId !== id) {
    return <Navigate to="/recipes" replace />;
  }

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
              // Lower-cased: the name is mid-sentence here, not a heading of its own.
              recipe.name.toLocaleLowerCase(),
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

      <div
        className="fp-progress-track"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={progress.total}
        aria-valuenow={progress.done}
      >
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
                {done && <Icon name="check" size={11} stroke={3.6} />}
              </span>
              <span>{labelFor(step.resource)}</span>
              {/*
                One number, the way the mock had it: the rows this step will create. A running
                "40 / 240" invites reading the pair as progress within the step, which it is
                not — the bar above is the progress, and the dot says which state this row is in.
              */}
              <span className="fp-recipe-step-n mono">{wanted.toLocaleString()}</span>
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
