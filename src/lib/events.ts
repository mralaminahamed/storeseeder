/**
 * Cross-component window events.
 *
 * The alternative was threading a callback from AppShell down through the router to
 * one page, which would make every route between them carry a prop it does not use.
 * Same approach as CONSENT_CHANGED_EVENT in lib/consent.ts.
 */

/** Ask AppShell to open the Tweaks panel. */
export const OPEN_TWEAKS_EVENT = "storeseeder:open-tweaks";

export function requestTweaksPanel(): void {
  window.dispatchEvent(new CustomEvent(OPEN_TWEAKS_EVENT));
}
