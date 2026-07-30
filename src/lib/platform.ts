import apiFetch from "@wordpress/api-fetch";
import { __, sprintf } from "@wordpress/i18n";
import type { Capability, PlatformInfo, PlatformState } from "@/types";

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

export function fetchPlatforms(): Promise<PlatformState> {
  return apiFetch({ path: "/storeseeder/v1/platforms" });
}

export function setTargetPlatform(platform: string): Promise<PlatformState> {
  return apiFetch({
    path: "/storeseeder/v1/platforms/target",
    method: "POST",
    data: { platform: platform === AUTO ? AUTO : platform },
  });
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
 * On auto this names what auto resolved to, so the target is never invisible —
 * "Auto" alone tells you nothing about where rows are going.
 */
export function targetLabel(state: PlatformState, selected: string): string {
  if (selected !== AUTO && selected !== "") {
    return findPlatform(state, selected)?.label ?? selected;
  }

  const resolved = findPlatform(state, state.resolved);

  if (!resolved) return __("Auto", "storeseeder");

  return sprintf(
    /* translators: %s: name of the platform that Auto resolved to. */
    __("Auto · %s", "storeseeder"),
    resolved.label,
  );
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
    }
  );
}
