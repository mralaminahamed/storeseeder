/**
 * The data StoreSeeder created, and removing it.
 *
 * Counts come from the plugin's own ledger of rows it wrote — not from the store's tables —
 * so what this reports is exactly what deletion would remove. That is the whole reason the
 * feature can be offered: "delete everything that looks like test data" would eventually
 * take out something real on a staging site restored from production.
 *
 * Deletion is batched server-side. `deleteGenerated` does one batch and reports what is
 * left; `purgeGenerated` loops until nothing is, reporting progress as it goes.
 */

export interface GeneratedResource {
  /** Canonical resource name, e.g. `product` or `cart_session`. */
  resource: string;
  count: number;
}

export interface GeneratedState {
  total: number;
  /** In deletion order — children before the parents they hang off. */
  resources: GeneratedResource[];
  /** Platform ids the rows were written to. */
  platforms: string[];
  /** Rows the server deletes per request. */
  batch: number;
}

export interface PurgeResult {
  deleted: number;
  /** Records dropped without deleting rows, for the forget path. */
  forgotten: number;
  remaining: number;
  byResource: Record<string, number>;
  /** One message per distinct reason, not per row. */
  errors: string[];
}

function api(): { restUrl: string; nonce: string } {
  return {
    restUrl: window.storeseederApi?.restUrl ?? "",
    nonce: window.storeseederApi?.restNonce ?? "",
  };
}

const num = (value: unknown): number =>
  "number" === typeof value && Number.isFinite(value) ? value : 0;

/**
 * Read a ledger payload without trusting its shape — a changed endpoint, or a PHP fatal
 * returning HTML, should read as "nothing recorded" rather than throw past the caller.
 */
export function parseGenerated(body: unknown): GeneratedState {
  const source =
    null !== body && "object" === typeof body
      ? (body as Record<string, unknown>)
      : {};

  const resources: GeneratedResource[] = [];

  if (Array.isArray(source.resources)) {
    for (const entry of source.resources) {
      if (null === entry || "object" !== typeof entry) continue;
      const row = entry as Record<string, unknown>;
      if ("string" !== typeof row.resource) continue;
      resources.push({ resource: row.resource, count: num(row.count) });
    }
  }

  return {
    total: num(source.total),
    resources,
    platforms: Array.isArray(source.platforms)
      ? source.platforms.filter((p): p is string => "string" === typeof p)
      : [],
    // 100 mirrors Purge::BATCH; only used if the server did not say.
    batch: 0 < num(source.batch) ? num(source.batch) : 100,
  };
}

export function parsePurge(body: unknown): PurgeResult {
  const source =
    null !== body && "object" === typeof body
      ? (body as Record<string, unknown>)
      : {};

  const byResource: Record<string, number> = {};
  if (null !== source.by_resource && "object" === typeof source.by_resource) {
    for (const [resource, count] of Object.entries(
      source.by_resource as Record<string, unknown>,
    )) {
      byResource[resource] = num(count);
    }
  }

  return {
    deleted: num(source.deleted),
    forgotten: num(source.forgotten),
    remaining: num(source.remaining),
    byResource,
    errors: Array.isArray(source.errors)
      ? source.errors.filter((e): e is string => "string" === typeof e)
      : [],
  };
}

export async function fetchGenerated(): Promise<GeneratedState> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}generated`, {
    headers: { "X-WP-Nonce": nonce },
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parseGenerated(await res.json());
}

/** Delete one batch. `resource` empty means every resource. */
export async function deleteGenerated(
  resource = "",
  forget = false,
): Promise<PurgeResult> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}generated`, {
    method: "DELETE",
    headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
    body: JSON.stringify({ resource, forget }),
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parsePurge(await res.json());
}

/**
 * Delete every batch, reporting progress between them.
 *
 * Stops when a round deletes nothing while rows remain: those rows are all failing for the
 * same reason — a deactivated plugin, most likely — and another round fails identically.
 * Without that guard this loops for ever on a resource whose writer refuses.
 */
export async function purgeGenerated(
  resource = "",
  onProgress?: (deleted: number, remaining: number) => void,
): Promise<PurgeResult> {
  let deleted = 0;
  const errors: string[] = [];
  const byResource: Record<string, number> = {};
  let remaining = 0;

  // A ceiling rather than `while (true)`: a server that keeps reporting work left would
  // otherwise hold the page open indefinitely.
  for (let round = 0; round < 500; round++) {
    const batch = await deleteGenerated(resource);

    deleted += batch.deleted;
    remaining = batch.remaining;

    for (const [key, count] of Object.entries(batch.byResource)) {
      byResource[key] = (byResource[key] ?? 0) + count;
    }
    for (const error of batch.errors) {
      if (!errors.includes(error)) errors.push(error);
    }

    onProgress?.(deleted, remaining);

    if (0 === remaining || 0 === batch.deleted) break;
  }

  return { deleted, forgotten: 0, remaining, byResource, errors };
}
