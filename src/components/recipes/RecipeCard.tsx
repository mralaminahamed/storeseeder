import React from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";
import {
  labelFor,
  scaledCount,
  skippedSteps,
  type Recipe,
} from "@/lib/recipes";

interface RecipeCardProps {
  recipe: Recipe;
  size: string;
  selected: boolean;
  onSelect: (id: string) => void;
}

/**
 * One recipe, and everything the click commits you to.
 *
 * There is no preview table on this page and there should not be: a preview shows one resource's
 * rows and a recipe spans nine. The itemised counts are what does that job instead — which is why
 * they are per resource rather than a single total, and why they are filtered by what the target
 * will actually accept.
 */
export function RecipeCard({ recipe, size, selected, onSelect }: RecipeCardProps) {
  const skipped = skippedSteps(recipe);

  return (
    <button
      type="button"
      className={`fp-recipe fp-focusable${selected ? " on" : ""}`}
      aria-pressed={selected}
      data-recipe={recipe.id}
      style={{ ["--recipe-hue" as string]: `var(--${recipe.accent})` }}
      onClick={() => onSelect(recipe.id)}
    >
      <div className="fp-recipe-top">
        <span className="fp-recipe-ic">
          {/*
            An <img>, not inlined markup. The mark comes from a downloaded archive that a site may
            have repointed at a fork, so it is third-party content in wp-admin — and an <img> is a
            passive context where script cannot run even if the server-side filter were bypassed.
            The trade is that currentColor does not reach it, which is why a mark carries its own
            colours and only the tile tint comes from the accent token.
          */}
          {recipe.icon_uri ? (
            <img className="fp-recipe-mark" src={recipe.icon_uri} alt="" width={26} height={26} />
          ) : (
            <Icon name={recipe.icon as IconName} size={21} />
          )}
        </span>
        <span className="fp-recipe-head">
          <span className="fp-recipe-name">{recipe.name}</span>
          <span className="fp-recipe-desc">{recipe.description}</span>
        </span>
      </div>

      <div className="fp-recipe-meta">
        <span className="fp-recipe-chip mono">{recipe.locales.join(" · ")}</span>

        {/*
          Said before the click, not after. A recipe that ships no vocabulary for the chosen locale
          still runs — FakerPHP produces local names and addresses either way — but the product
          titles come from the fallback, and staying quiet about that is how the locale picker came
          to offer seventy-three and deliver one.
        */}
        {!recipe.locale_shipped && (
          <span className="fp-recipe-chip warn">
            <Icon name="alert" size={11} />
            {sprintf(
              /* translators: %s: locale code, e.g. en_US. */
              __("Product names stay %s", "storeseeder"),
              recipe.fallback_locale,
            )}
          </span>
        )}
      </div>

      {/*
        What the manifest claims, against the files that arrived. Every one of these produces data
        rather than an error, so the card is the only place they can surface — a recipe with no
        vocabulary builds a shop that looks fine until someone reads the product names.
      */}
      {recipe.issues.length > 0 && (
        <div className="fp-recipe-issues">
          {recipe.issues.map((issue) => (
            <span
              key={issue.code}
              className={`fp-recipe-issue${issue.blocking ? " blocking" : ""}`}
            >
              <Icon name={issue.blocking ? "alert" : "info"} size={13} />
              <span>{issue.message}</span>
            </span>
          ))}
        </div>
      )}

      <div className="fp-recipe-counts">
        {recipe.plan.map((step) => {
          const refused = false === step.supported;

          return (
            <span
              key={step.resource}
              className={`fp-recipe-count${refused ? " off" : ""}`}
            >
              <span className="fp-recipe-count-label">{labelFor(step.resource)}</span>
              <span className="fp-recipe-count-n mono">
                {refused ? "—" : scaledCount(step, size).toLocaleString()}
              </span>
            </span>
          );
        })}

        {/*
          The driver's own refusal, verbatim. A card promising nine hundred transactions that
          silently produces zero is exactly what Capability::unsupported() exists to prevent, and
          the reason is more use than the absence.
        */}
        {skipped.length > 0 && (
          <span className="fp-recipe-skip">
            <Icon name="info" size={13} />
            <span>
              <strong>{skipped.map((s) => labelFor(s.resource)).join(", ")}</strong>{" "}
              {skipped[0].reason ||
                __("cannot be created on the selected platform.", "storeseeder")}
              {skipped[0].extension
                ? ` ${sprintf(
                    /* translators: %s: plugin name that would enable the resource. */
                    __("Install %s to enable it.", "storeseeder"),
                    skipped[0].extension,
                  )}`
                : ""}
            </span>
          </span>
        )}
      </div>
    </button>
  );
}

export default RecipeCard;
