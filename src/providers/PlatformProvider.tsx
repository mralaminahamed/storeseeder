import React from "react";
import { createContext, useContext, useState, useCallback } from "@wordpress/element";
import {
  AUTO,
  activePlatforms,
  capabilityFor,
  initialPlatformState,
  setTargetPlatform,
  targetLabel,
} from "@/lib/platform";
import type { Capability, PlatformInfo, PlatformState } from "@/types";

// ---------------------------------------------------------------------------
// Context shape
// ---------------------------------------------------------------------------

interface PlatformContextState {
  /** Everything the server knows about the installed platforms. */
  state: PlatformState;
  /** The topbar selection: a platform id, or AUTO. */
  selected: string;
  /** Platforms that are installed and loaded. */
  active: PlatformInfo[];
  /** True when more than one platform is active and none has been chosen. */
  ambiguous: boolean;
  /** Label for the current selection, naming what Auto resolved to. */
  label: string;
  /**
   * The platform a run would write to, or null when that cannot be decided yet.
   * Sent as the `platform` parameter, so null means the caller must ask first.
   */
  target: string | null;
  /** Change the site-wide target. Persists through REST. */
  setTarget: (platform: string) => Promise<void>;
  /** Whether one resource can be generated on the current target. */
  capability: (resource: string) => Capability | null;
}

const PlatformContext = createContext<PlatformContextState | null>(null);

// ---------------------------------------------------------------------------
// Provider
// ---------------------------------------------------------------------------

export function PlatformProvider({ children }: { children: React.ReactNode }) {
  // Seeded from the inlined payload rather than fetched, so the topbar never
  // renders a target it then has to correct.
  const [state, setState] = useState<PlatformState>(() => initialPlatformState());
  const [selected, setSelected] = useState<string>(() => initialPlatformState().stored || AUTO);

  const setTarget = useCallback(async (platform: string) => {
    // Optimistic, because the select should not feel laggy — but the server's
    // answer is authoritative, since it also recomputes `resolved`.
    setSelected(platform);

    try {
      const next = await setTargetPlatform(platform);

      // null when the body was not a platform state. Keeping the current state is the
      // only safe move: assigning the body would leave `state.platforms` undefined, and
      // everything reading it — the topbar, the sidebar, the capability checks — would
      // throw on the next render.
      if (next) {
        setState(next);
        setSelected(next.stored || AUTO);
      }
    } catch {
      // Leave the optimistic selection in place. A failed write means the site
      // option is unchanged, which the next page load will reveal; discarding the
      // user's choice here would be more confusing than keeping it.
    }
  }, []);

  const capability = useCallback(
    (resource: string) => capabilityFor(state, selected, resource),
    [state, selected]
  );

  const target = selected !== AUTO ? selected : state.resolved;

  return (
    <PlatformContext.Provider
      value={{
        state,
        selected,
        active: activePlatforms(state),
        ambiguous: state.ambiguous && selected === AUTO,
        label: targetLabel(state, selected),
        target,
        setTarget,
        capability,
      }}
    >
      {children}
    </PlatformContext.Provider>
  );
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

export function usePlatform(): PlatformContextState {
  const ctx = useContext(PlatformContext);
  if (!ctx) throw new Error("usePlatform must be used within PlatformProvider");
  return ctx;
}
