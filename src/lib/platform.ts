import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import type {
  Capability,
  ParameterConfig,
  PlatformInfo,
  PlatformState,
} from "@/types";

/**
 * The value meaning "decide for me". Sent verbatim to the REST API, which
 * resolves it against the stored site setting and the active platforms.
 */
export const AUTO = "auto";

const EMPTY: PlatformState = {
  platforms: [],
  stored: "",
  resolved: null,
  ambiguous: false,
};

/**
 * Read the state the server inlined into the page.
 *
 * Preferred over fetching: the topbar shows which store a run will write to, and
 * a blank-then-correct render there is worse than a slightly later first paint.
 */
export function initialPlatformState(): PlatformState {
  return window.storeseederApi?.platforms ?? EMPTY;
}

/**
 * Whether a REST body is really a platform state.
 *
 * Everything downstream reads `state.platforms` without checking, so a body missing it
 * takes the whole admin down with a white screen. That is not hypothetical: a filtered
 * response, an error envelope, or an older server all arrive here as "something else",
 * and the same guard pattern is already used for the sample-data status and the access
 * payload.
 */
export function isPlatformState(value: unknown): value is PlatformState {
  if (null === value || "object" !== typeof value) return false;

  const state = value as Record<string, unknown>;

  return Array.isArray(state.platforms) && "string" === typeof state.stored;
}

/**
 * Narrow a REST body to a platform state, or return null when it is not one.
 *
 * Null rather than an empty state: the caller already has a usable state, and replacing it
 * with an empty one would blank the topbar and claim no platform is installed.
 */
export function parsePlatformState(value: unknown): PlatformState | null {
  return isPlatformState(value) ? value : null;
}

export function setTargetPlatform(
  platform: string,
): Promise<PlatformState | null> {
  return apiFetch({
    path: "/storeseeder/v1/platforms/target",
    method: "POST",
    data: { platform: platform === AUTO ? AUTO : platform },
  }).then(parsePlatformState);
}

export function activePlatforms(state: PlatformState): PlatformInfo[] {
  return state.platforms.filter((p) => p.active);
}

export function findPlatform(state: PlatformState, id: string | null): PlatformInfo | undefined {
  if (!id) return undefined;
  return state.platforms.find((p) => p.id === id);
}

/**
 * The label to show for the current selection.
 *
 * Auto is always the word "Auto", never "Auto · Fluent Cart". It used to carry what it
 * resolved to, on the reasoning that the target should never be invisible — but the option
 * is a *mode*, and a mode whose name changes with the store it happens to have picked reads
 * as a different option each time the picker is opened. Where the rows are going is on the
 * control's tooltip and on the Settings page instead.
 */
export function targetLabel(state: PlatformState, selected: string): string {
  if (selected !== AUTO && selected !== "") {
    return findPlatform(state, selected)?.label ?? selected;
  }

  return __("Auto", "storeseeder");
}

/**
 * Whether a resource can be generated on the resolved target.
 *
 * Returns null when there is no target yet, which is not the same as "no": the
 * caller should prompt for a target rather than dim the generator.
 */
export function capabilityFor(
  state: PlatformState,
  selected: string,
  resource: string,
): Capability | null {
  const id = selected !== AUTO && selected !== "" ? selected : state.resolved;
  const platform = findPlatform(state, id);

  if (!platform) return null;

  return (
    platform.supports[resource] ?? {
      supported: false,
      reason: __("Not supported by this platform.", "storeseeder"),
      extension: "",
      ignored_fields: [],
    }
  );
}

/**
 * The resolved target's own extra parameters for one resource.
 *
 * Only the target's: a field belonging to another platform would render as a control that the
 * run then ignores, which is the failure this whole mechanism exists to stop. Empty when there is
 * no target yet, so the form shows the canonical fields and nothing speculative.
 */
export function platformFieldsFor(
  state: PlatformState,
  selected: string,
  resource: string,
): Record<string, ParameterConfig> {
  const id = selected !== AUTO && selected !== "" ? selected : state.resolved;
  const platform = findPlatform(state, id);

  return platform?.fields?.[resource] ?? {};
}
