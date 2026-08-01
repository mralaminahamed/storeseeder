/**
 * Splitting a requested count into requests the endpoint will accept.
 *
 * `count` is capped at 100 by the generate route's own schema, and the cap is not advisory: WordPress
 * rejects the request during argument validation, so a count of 500 came back as
 * `Invalid parameter(s): count` with nothing written. Meanwhile the run bar's stepper allowed up to
 * 100,000 — a control offering three orders of magnitude more than the API would take.
 *
 * The recipe runner had always split its plan here; the single-run and batch paths had not, which is
 * why they broke at 101 and recipes of nine hundred orders did not.
 *
 * `CHUNK` lives here rather than in `recipes.ts` so that one number is one number. Two copies of a
 * limit that must match the server's is the shape of every silent drift in this codebase.
 */

/** The generate route's `count` maximum, from `Rest\Controller::get_generation_params()`. */
export const CHUNK = 100;

/**
 * The per-request counts a total splits into.
 *
 * `chunkCounts( 250 )` is `[100, 100, 50]`. A total at or below the cap is a single request, so the
 * common case adds no round trips. Zero or less is no requests at all rather than one of zero, which
 * the schema would also reject (`minimum: 1`).
 */
export function chunkCounts(total: number): number[] {
  const counts: number[] = [];

  for (let left = Math.floor(total); left > 0; left -= CHUNK) {
    counts.push(Math.min(CHUNK, left));
  }

  return counts;
}
