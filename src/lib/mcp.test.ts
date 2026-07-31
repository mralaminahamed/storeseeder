import { describe, expect, it, jest } from "@jest/globals";

import { fetchMcp, parseMcpStatus, saveMcpToggle } from "./mcp";

/** A response with the given JSON body, as `fetch` would return it. */
function respond(body: unknown, ok = true, status = 200): Response {
  return {
    ok,
    status,
    json: () => Promise.resolve(body),
  } as unknown as Response;
}

function mockFetch(response: Response) {
  const fetchMock = jest.fn<typeof fetch>().mockResolvedValue(response);
  window.fetch = fetchMock;
  return fetchMock;
}

const FULL = {
  available: true,
  abilities_api: true,
  adapter: true,
  abilities: 18,
  tools: 36,
  enabled: true,
  preview: true,
  generate: true,
  can_manage: true,
  route: "https://example.test/wp-json/storeseeder-mcp/mcp",
};

describe("parseMcpStatus", () => {
  it("returns the payload", () => {
    expect(parseMcpStatus(FULL)).toEqual(FULL);
  });

  /**
   * Every boolean defaults to false, which is the safe direction: an unreadable payload
   * must not render three switches as on, nor claim tools are exposed.
   */
  it("reads an unrecognised body as nothing available", () => {
    expect(parseMcpStatus("<html>fatal error</html>")).toEqual({
      available: false,
      abilities_api: false,
      adapter: false,
      abilities: 0,
      tools: 0,
      enabled: false,
      preview: false,
      generate: false,
      can_manage: false,
      route: "",
    });
  });

  it("ignores counts that are not finite numbers", () => {
    const status = parseMcpStatus({ abilities: "18", tools: NaN });

    expect(status.abilities).toBe(0);
    expect(status.tools).toBe(0);
  });
});

describe("fetchMcp", () => {
  it("sends the nonce to the mcp route", async () => {
    const fetchMock = mockFetch(respond(FULL));

    await fetchMcp();

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/mcp",
      { headers: { "X-WP-Nonce": "test-nonce" } },
    );
  });

  it("throws on a failed request so the caller can restore what it showed", async () => {
    mockFetch(respond({}, false, 403));

    await expect(fetchMcp()).rejects.toThrow("HTTP 403");
  });
});

describe("saveMcpToggle", () => {
  /**
   * One key per request: the server leaves an absent toggle alone, so two administrators
   * changing different switches cannot undo each other.
   */
  it("sends only the switch that moved", async () => {
    const fetchMock = mockFetch(respond({ ...FULL, generate: false, tools: 18 }));

    await saveMcpToggle("generate", false);

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/mcp",
      expect.objectContaining({ body: JSON.stringify({ generate: false }) }),
    );
  });

  it("returns what the server stored, not what was asked for", async () => {
    mockFetch(respond({ ...FULL, enabled: false, tools: 0 }));

    const status = await saveMcpToggle("enabled", true);

    expect(status.enabled).toBe(false);
    expect(status.tools).toBe(0);
  });
});
