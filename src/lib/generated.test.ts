import { describe, expect, it, jest } from "@jest/globals";

import {
  deleteGenerated,
  fetchGenerated,
  parseGenerated,
  parsePurge,
  purgeGenerated,
} from "./generated";

/** A response with the given JSON body, as `fetch` would return it. */
function respond(body: unknown, ok = true, status = 200): Response {
  return {
    ok,
    status,
    json: () => Promise.resolve(body),
  } as unknown as Response;
}

function mockFetch(...responses: Response[]) {
  const fetchMock = jest.fn<typeof fetch>();
  for (const response of responses) fetchMock.mockResolvedValueOnce(response);
  // Repeat the last one, so a loop that overruns is a failed assertion rather than a
  // rejected promise from an exhausted mock.
  fetchMock.mockResolvedValue(responses[responses.length - 1]);
  window.fetch = fetchMock;
  return fetchMock;
}

const LEDGER = {
  total: 3,
  resources: [
    { resource: "transaction", count: 1 },
    { resource: "order", count: 2 },
  ],
  platforms: ["fluent-cart"],
  batch: 100,
};

describe("parseGenerated", () => {
  it("returns the payload", () => {
    expect(parseGenerated(LEDGER)).toEqual(LEDGER);
  });

  /**
   * Nothing recorded is the safe reading: the card then offers no delete button rather than
   * one with a made-up count on it.
   */
  it("reads an unrecognised body as nothing recorded", () => {
    expect(parseGenerated("<html>fatal error</html>")).toEqual({
      total: 0,
      resources: [],
      platforms: [],
      batch: 100,
    });
  });

  it("drops resource entries it cannot read", () => {
    const state = parseGenerated({
      resources: [{ count: 5 }, "order", { resource: "product" }],
    });

    expect(state.resources).toEqual([{ resource: "product", count: 0 }]);
  });
});

describe("parsePurge", () => {
  it("reads the counts and the per-resource breakdown", () => {
    const result = parsePurge({
      deleted: 2,
      remaining: 1,
      forgotten: 0,
      by_resource: { order: 2 },
      errors: ["nope"],
    });

    expect(result).toEqual({
      deleted: 2,
      forgotten: 0,
      remaining: 1,
      byResource: { order: 2 },
      errors: ["nope"],
    });
  });
});

describe("fetchGenerated", () => {
  it("sends the nonce to the generated route", async () => {
    const fetchMock = mockFetch(respond(LEDGER));

    await fetchGenerated();

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/generated",
      { headers: { "X-WP-Nonce": "test-nonce" } },
    );
  });
});

describe("deleteGenerated", () => {
  it("asks the server to delete, not to forget", async () => {
    const fetchMock = mockFetch(respond({ deleted: 1, remaining: 0 }));

    await deleteGenerated();

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/generated",
      expect.objectContaining({
        method: "DELETE",
        body: JSON.stringify({ resource: "", forget: false }),
      }),
    );
  });

  it("passes a resource through", async () => {
    const fetchMock = mockFetch(respond({ deleted: 1, remaining: 0 }));

    await deleteGenerated("product");

    expect(fetchMock).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        body: JSON.stringify({ resource: "product", forget: false }),
      }),
    );
  });
});

describe("purgeGenerated", () => {
  it("keeps going until nothing is left, and totals what it deleted", async () => {
    mockFetch(
      respond({ deleted: 2, remaining: 2, by_resource: { order: 2 } }),
      respond({ deleted: 2, remaining: 0, by_resource: { order: 2 } }),
    );

    const progress: Array<[number, number]> = [];
    const result = await purgeGenerated("", (deleted, remaining) =>
      progress.push([deleted, remaining]),
    );

    expect(result.deleted).toBe(4);
    expect(result.remaining).toBe(0);
    expect(result.byResource).toEqual({ order: 4 });
    expect(progress).toEqual([
      [2, 2],
      [4, 0],
    ]);
  });

  /**
   * The guard that matters: a round that deletes nothing while rows remain means every
   * remaining row is failing for the same reason, and looping would hold the page open for
   * ever waiting for a number that never moves.
   */
  it("stops when a round deletes nothing", async () => {
    const fetchMock = mockFetch(
      respond({ deleted: 0, remaining: 5, errors: ["Pro is not active."] }),
    );

    const result = await purgeGenerated();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(result.deleted).toBe(0);
    expect(result.remaining).toBe(5);
    expect(result.errors).toEqual(["Pro is not active."]);
  });

  it("reports each distinct reason once", async () => {
    mockFetch(
      respond({ deleted: 1, remaining: 1, errors: ["same reason"] }),
      respond({ deleted: 1, remaining: 0, errors: ["same reason"] }),
    );

    const result = await purgeGenerated();

    expect(result.errors).toEqual(["same reason"]);
  });
});
