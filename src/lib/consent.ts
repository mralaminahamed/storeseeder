/**
 * Cross-component messaging for the sample-data consent prompt.
 *
 * The consent modal is mounted once in the app shell and normally self-gates:
 * it appears only when the site-wide decision is still undecided AND no sample
 * data is present, so a site that has already decided is never nagged.
 *
 * That gate leaves no way to ask again once a decision exists. Rather than a
 * standalone "show the prompt" control, Settings routes its download buttons
 * through the prompt whenever consent has not been granted — the download is
 * what needs permission, so that is where the question belongs.
 */
export const SHOW_CONSENT_EVENT = "storeseeder:show-consent-prompt";

/**
 * Fired by the modal once a decision has been recorded, so any page showing
 * consent state can refetch instead of going stale.
 */
export const CONSENT_CHANGED_EVENT = "storeseeder:consent-changed";

/**
 * Ask the mounted consent modal to open, regardless of the recorded decision.
 */
export function requestConsentPrompt(): void {
  window.dispatchEvent(new CustomEvent(SHOW_CONSENT_EVENT));
}

/**
 * Announce that the stored consent decision changed.
 */
export function notifyConsentChanged(): void {
  window.dispatchEvent(new CustomEvent(CONSENT_CHANGED_EVENT));
}

/**
 * What `download-sample` reports about the sample data and the consent decision.
 *
 * Settings and the modal each declared their own copy of this shape and then
 * assigned an unvalidated `res.json()` to it, so a changed endpoint would have
 * been a runtime surprise rather than a type error. One declaration, one parser.
 */
export interface SampleDataStatus {
  exists: boolean;
  last_synced: string | null;
  repo_url: string;
  consent: "granted" | "declined" | null;
}

/** A REST body, before anything is known about its contents. */
type JsonBody = Record<string, unknown>;

/**
 * Read a JSON body without trusting its shape. A non-object body — or a response
 * that is not JSON at all, which is what a PHP fatal looks like — reads as empty
 * rather than throwing past the caller's error handling.
 */
export async function readJsonBody(res: Response): Promise<JsonBody> {
  try {
    const parsed: unknown = await res.json();
    return null !== parsed && "object" === typeof parsed
      ? (parsed as JsonBody)
      : {};
  } catch {
    return {};
  }
}

/** The `message` a REST error carries, or `fallback` when it carries none. */
export function bodyMessage(body: JsonBody, fallback: string): string {
  return "string" === typeof body.message && body.message
    ? body.message
    : fallback;
}

/** Narrow a REST body to the status shape, or null if it does not match. */
export function parseSampleDataStatus(body: JsonBody): SampleDataStatus | null {
  if ("boolean" !== typeof body.exists) {
    return null;
  }

  const consent = body.consent;

  return {
    exists: body.exists,
    last_synced: "string" === typeof body.last_synced ? body.last_synced : null,
    repo_url: "string" === typeof body.repo_url ? body.repo_url : "",
    consent:
      "granted" === consent || "declined" === consent ? consent : null,
  };
}
