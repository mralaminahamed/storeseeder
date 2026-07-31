import { describe, expect, it, jest } from "@jest/globals";

import { fetchAccess, saveAllowedRoles } from "./access";

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
  capability: "manage_options",
  filtered: false,
  adminRole: "administrator",
  roles: { editor: "Editor", author: "Author" },
  allowedRoles: ["editor"],
  canManage: true,
};

describe("fetchAccess", () => {
  it("sends the nonce to the access route", async () => {
    const fetchMock = mockFetch(respond(FULL));

    await fetchAccess();

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/access",
      { headers: { "X-WP-Nonce": "test-nonce" } },
    );
  });

  it("returns the payload", async () => {
    mockFetch(respond(FULL));

    await expect(fetchAccess()).resolves.toEqual(FULL);
  });

  /**
   * The body is parsed rather than asserted, the same reasoning as the sample-data
   * status: a changed endpoint, or a PHP fatal returning HTML, should read as empty
   * instead of throwing somewhere far from here.
   */
  describe("a body that is not the expected shape", () => {
    it("falls back field by field", async () => {
      mockFetch(respond({ roles: "not an object", allowedRoles: "editor" }));

      await expect(fetchAccess()).resolves.toEqual({
        capability: "manage_options",
        filtered: false,
        adminRole: "administrator",
        roles: {},
        allowedRoles: [],
        canManage: false,
      });
    });

    it("keeps only the string entries of roles and allowedRoles", async () => {
      mockFetch(
        respond({
          roles: { editor: "Editor", broken: 42 },
          allowedRoles: ["editor", 7, null],
        }),
      );

      const access = await fetchAccess();

      expect(access.roles).toEqual({ editor: "Editor" });
      expect(access.allowedRoles).toEqual(["editor"]);
    });

    /**
     * canManage decides whether the card renders read-only. Defaulting it to true on a
     * malformed body would show controls that 403 on use, so it defaults to false.
     */
    it("defaults canManage to false", async () => {
      mockFetch(respond({}));

      await expect(fetchAccess()).resolves.toMatchObject({ canManage: false });
    });
  });

  it("rejects on an HTTP error rather than returning a hollow payload", async () => {
    mockFetch(respond({ code: "rest_forbidden" }, false, 403));

    await expect(fetchAccess()).rejects.toThrow("HTTP 403");
  });
});

describe("saveAllowedRoles", () => {
  it("POSTs the roles with the nonce", async () => {
    const fetchMock = mockFetch(respond(FULL));

    await saveAllowedRoles(["editor", "author"]);

    expect(fetchMock).toHaveBeenCalledWith(
      "https://example.test/wp-json/storeseeder/v1/access",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": "test-nonce",
        },
        body: JSON.stringify({ roles: ["editor", "author"] }),
      },
    );
  });

  /**
   * The server drops roles the site no longer defines, so its response — not the request
   * — is what the UI must show.
   */
  it("returns what the server stored, not what was sent", async () => {
    mockFetch(respond({ ...FULL, allowedRoles: ["editor"] }));

    const access = await saveAllowedRoles(["editor", "wizard"]);

    expect(access.allowedRoles).toEqual(["editor"]);
  });

  it("rejects on a 403 so the caller can restore and warn", async () => {
    mockFetch(respond({}, false, 403));

    await expect(saveAllowedRoles(["editor"])).rejects.toThrow("HTTP 403");
  });
});
