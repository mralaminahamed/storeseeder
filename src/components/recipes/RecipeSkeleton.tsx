import React from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import { Skeleton } from "@/components/ui/Skeleton";

/**
 * The shape of the page while the recipes are still coming.
 *
 * A spinner says "wait"; a skeleton says "wait, and here is what for". This one is built from the
 * real card's own classes — same tile, same two-column count grid, same run bar beneath — so
 * nothing moves when the data lands. That reflow is the cost a centred spinner hides.
 *
 * Four cards and eight count rows because that is what the shipped archive actually holds. A
 * skeleton that guesses low still jumps.
 *
 * The boxes are `aria-hidden` by `Skeleton` itself; the live region below carries the news to a
 * screen reader, which would otherwise be read two dozen empty spans.
 */
export function RecipeSkeleton() {
  return (
    <div aria-busy="true">
      <span className="sr-only" role="status">
        {__("Loading recipes…", "storeseeder")}
      </span>

      <div className="fp-recipe-grid" aria-hidden="true">
        {[0, 1, 2, 3].map((card) => (
          <div key={card} className="fp-recipe is-skeleton">
            <div className="fp-recipe-top">
              <Skeleton width={44} height={44} radius={11} />
              <span className="fp-recipe-head">
                <Skeleton width="42%" height={13} />
                <Skeleton width="94%" height={10} style={{ marginTop: 8 }} />
                <Skeleton width="66%" height={10} style={{ marginTop: 5 }} />
              </span>
            </div>

            <div className="fp-recipe-meta">
              <Skeleton width={62} height={17} radius={999} />
              <Skeleton width={96} height={17} radius={999} />
            </div>

            <div className="fp-recipe-counts">
              {[0, 1, 2, 3, 4, 5, 6, 7].map((row) => (
                <span key={row} className="fp-recipe-count">
                  <Skeleton width="58%" height={10} />
                  <Skeleton className="fp-recipe-count-n" width={26} height={10} />
                </span>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div className="fp-recipe-bar" aria-hidden="true">
        <Skeleton width={132} height={29} radius={8} />
        <Skeleton width={180} height={11} />
        <span className="fp-recipe-actions">
          <Skeleton width={150} height={38} radius={8} />
        </span>
      </div>
    </div>
  );
}

export default RecipeSkeleton;
