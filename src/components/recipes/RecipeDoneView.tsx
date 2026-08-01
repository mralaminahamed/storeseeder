import React from "@wordpress/element";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { __, _n, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import { labelFor } from "@/lib/recipes";
import { Button } from "@/components/ui/button";
import { useRecipes } from "@/components/recipes/context";

/** What a run produced. `/recipes/:id/done`. */
export default function RecipeDoneView() {
  const { id = "" } = useParams();
  const navigate = useNavigate();
  const { recipes, progress, undoing, undo, homeUrl } = useRecipes();

  const recipe = recipes.find((r) => r.id === id) ?? null;

  // The tally lives in memory with the run, so a reload has nothing to show. Back to the list
  // rather than an empty result panel claiming a build that this page no longer knows about.
  if (!recipe || !progress || progress.recipeId !== id) {
    return <Navigate to="/recipes" replace />;
  }

  const { undone, runId } = progress;
  const written = Object.values(progress.rows).reduce((t, n) => t + n, 0);
  const resources = Object.keys(progress.rows).length;

  return (
    <div className="fp-card fp-recipe-done">
      <div className="fp-recipe-done-head">
        <span className={`fp-recipe-done-ic${undone ? " undone" : ""}`}>
          <Icon name={undone ? "undo" : "check"} size={21} stroke={2.2} />
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
                    resources,
                    "storeseeder",
                  ),
                  written.toLocaleString(),
                  String(resources),
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
        {/*
          The front page rather than a per-driver storefront route: every platform puts its shop
          somewhere different, and a link that guesses wrong is worse than one that lands
          somewhere true. Hidden entirely when the site did not inline a URL.
        */}
        {!undone && homeUrl && (
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.open(homeUrl, "_blank", "noopener,noreferrer")}
          >
            <Icon name="external" size={13} />
            {__("View the store", "storeseeder")}
          </Button>
        )}
        <Button variant="outline" size="sm" onClick={() => void navigate("/recipes")}>
          {__("Build another", "storeseeder")}
        </Button>
        <span className="fp-recipe-spacer" />
        <span className="fp-recipe-runid mono">{runId}</span>
        {!undone && (
          <Button variant="outline" size="sm" disabled={undoing} onClick={() => void undo()}>
            <Icon name="undo" size={13} />
            {undoing ? __("Undoing…", "storeseeder") : __("Undo this recipe", "storeseeder")}
          </Button>
        )}
      </div>
    </div>
  );
}
