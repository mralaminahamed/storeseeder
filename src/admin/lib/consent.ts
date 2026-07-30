/**
 * Cross-component trigger for the sample-data consent prompt.
 *
 * The consent modal is mounted once in the app shell and normally self-gates:
 * it appears only when the site-wide decision is still undecided AND no sample
 * data is present, so a site that has already decided is never nagged.
 *
 * That gate makes the prompt unreachable once a decision exists, which is a
 * problem when someone wants to review it. Settings dispatches this event to
 * bring the prompt back on demand rather than reaching into the modal's state.
 */
export const SHOW_CONSENT_EVENT = "storeseeder:show-consent-prompt";

/**
 * Ask the mounted consent modal to open, regardless of the recorded decision.
 */
export function requestConsentPrompt(): void {
  window.dispatchEvent(new CustomEvent(SHOW_CONSENT_EVENT));
}
