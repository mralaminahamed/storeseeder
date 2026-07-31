import { describe, expect, it, jest } from "@jest/globals";

import {
  CONSENT_CHANGED_EVENT,
  SHOW_CONSENT_EVENT,
  bodyMessage,
  notifyConsentChanged,
  parseSampleDataStatus,
  readJsonBody,
  requestConsentPrompt,
} from "./consent";

describe("readJsonBody", () => {
  it("returns the parsed object", async () => {
    const res = { json: () => Promise.resolve({ exists: true }) } as Response;

    await expect(readJsonBody(res)).resolves.toEqual({ exists: true });
  });

  /**
   * What a PHP fatal looks like from here: a 200 with an HTML body. `res.json()` throws,
   * and the caller's own error handling should get an empty body rather than the throw.
   */
  it("returns an empty body when the response is not JSON", async () => {
    const res = {
      json: () => Promise.reject(new SyntaxError("Unexpected token <")),
    } as unknown as Response;

    await expect(readJsonBody(res)).resolves.toEqual({});
  });

  it.each([
    ["a JSON string", "just a string"],
    ["a number", 42],
    ["null", null],
  ])("returns an empty body for %s", async (_name, parsed) => {
    const res = { json: () => Promise.resolve(parsed) } as Response;

    await expect(readJsonBody(res)).resolves.toEqual({});
  });
});

describe("bodyMessage", () => {
  it("prefers the server's message", () => {
    expect(bodyMessage({ message: "Sync failed." }, "fallback")).toBe(
      "Sync failed.",
    );
  });

  it.each([
    ["a missing message", {}],
    ["an empty message", { message: "" }],
    ["a non-string message", { message: 500 }],
  ])("uses the fallback for %s", (_name, body) => {
    expect(bodyMessage(body, "fallback")).toBe("fallback");
  });
});

describe("parseSampleDataStatus", () => {
  it("narrows a full status body", () => {
    expect(
      parseSampleDataStatus({
        exists: true,
        last_synced: "2026-07-31T09:00:00+00:00",
        repo_url: "https://github.com/example/data",
        consent: "granted",
      }),
    ).toEqual({
      exists: true,
      last_synced: "2026-07-31T09:00:00+00:00",
      repo_url: "https://github.com/example/data",
      consent: "granted",
    });
  });

  /**
   * `exists` is the field the whole card branches on, so a body without it is not a
   * status at all — null, rather than a status claiming nothing is downloaded.
   */
  it("rejects a body with no usable exists flag", () => {
    expect(parseSampleDataStatus({})).toBeNull();
    expect(parseSampleDataStatus({ exists: "true" })).toBeNull();
  });

  it("nulls a missing or unusable last_synced", () => {
    expect(parseSampleDataStatus({ exists: false })?.last_synced).toBeNull();
    expect(
      parseSampleDataStatus({ exists: true, last_synced: 1690000000 })
        ?.last_synced,
    ).toBeNull();
  });

  it("empties an unusable repo_url rather than passing a number to href", () => {
    expect(parseSampleDataStatus({ exists: true, repo_url: 7 })?.repo_url).toBe(
      "",
    );
  });

  /**
   * Consent has three meanings and they are not interchangeable: granted, declined, and
   * never asked. Anything unrecognised has to become "never asked", which is the state
   * that prompts.
   */
  it.each([
    ["granted", "granted"],
    ["declined", "declined"],
    ["yes", null],
    ["", null],
    [undefined, null],
    [true, null],
  ])("reads consent %s as %s", (consent, expected) => {
    expect(parseSampleDataStatus({ exists: true, consent })?.consent).toBe(
      expected,
    );
  });
});

/**
 * Two events, easy to confuse and not interchangeable: one asks the modal to open, the
 * other announces that a decision was recorded so anything showing consent state can
 * refetch. Sending the wrong one either opens a prompt nobody asked for or leaves the
 * Settings card stale.
 */
describe("consent events", () => {
  it("requestConsentPrompt asks the modal to open", () => {
    const open = jest.fn();
    const changed = jest.fn();
    window.addEventListener(SHOW_CONSENT_EVENT, open);
    window.addEventListener(CONSENT_CHANGED_EVENT, changed);

    requestConsentPrompt();

    expect(open).toHaveBeenCalledTimes(1);
    expect(changed).not.toHaveBeenCalled();

    window.removeEventListener(SHOW_CONSENT_EVENT, open);
    window.removeEventListener(CONSENT_CHANGED_EVENT, changed);
  });

  it("notifyConsentChanged announces the decision", () => {
    const open = jest.fn();
    const changed = jest.fn();
    window.addEventListener(SHOW_CONSENT_EVENT, open);
    window.addEventListener(CONSENT_CHANGED_EVENT, changed);

    notifyConsentChanged();

    expect(changed).toHaveBeenCalledTimes(1);
    expect(open).not.toHaveBeenCalled();

    window.removeEventListener(SHOW_CONSENT_EVENT, open);
    window.removeEventListener(CONSENT_CHANGED_EVENT, changed);
  });
});
