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
