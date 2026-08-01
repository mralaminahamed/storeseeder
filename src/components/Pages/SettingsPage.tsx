import React from "react";
import { useState, useCallback, useEffect } from "@wordpress/element";
import { __, _n, sprintf } from "@wordpress/i18n";

import { Button } from "@/components/ui/button";
import { Seg } from "@/components/ui/Seg";
import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";
import { Toggle } from "@/components/generator/fields/Toggle";
import { NumberField } from "@/components/generator/fields/NumberField";
import { TextField } from "@/components/generator/fields/TextField";
import { FieldSelect } from "@/components/generator/fields/FieldSelect";
import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";
import {
  CONSENT_CHANGED_EVENT,
  requestConsentPrompt,
  readJsonBody,
  bodyMessage,
  parseSampleDataStatus,
} from "@/lib/consent";
import type { SampleDataStatus } from "@/lib/consent";
import { getSettings, saveSettings } from "@/lib/settings";
import { fetchAccess, saveAllowedRoles } from "@/lib/access";
import type { AccessState } from "@/lib/access";
import { fetchMcp, parseMcpStatus, saveMcpToggle } from "@/lib/mcp";
import type { McpToggle } from "@/lib/mcp";
import {
  fetchGenerated,
  deleteGenerated,
  purgeGenerated,
} from "@/lib/generated";
import type { GeneratedState } from "@/lib/generated";
import { resourceLabel } from "@/lib/generators";
import { requestTweaksPanel } from "@/lib/events";
import {
  DOCS,
  DOCS_URL,
  GITHUB_URL,
  ISSUES_URL,
  RECIPES_REPO_URL,
  SAMPLE_DATA_REPO_URL,
} from "@/lib/links";
import { DEFAULT_LOCALE, localeOptions } from "@/lib/locales";
import { AUTO } from "@/lib/platform";
import { usePlatform } from "@/providers/PlatformProvider";
import { fetchRecipesStatus, routeRows, syncRecipes } from "@/lib/recipes";
import type { RecipesStatus } from "@/lib/recipes";
import { ConfirmDialog } from "@/components/overlays/ConfirmDialog";
import { PageHead } from "@/components/ui/PageHead";
import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";
import { useTheme, type Density, type Theme } from "@/theme/useTheme";

// Localized from STORESEEDER_VERSION; the fallback only shows if the script
// data is missing, which would mean the admin app failed to enqueue properly.
const PLUGIN_VERSION = window.storeseederApi?.version || "—";
// Automattic's remote-MCP proxy: the package a desktop client is pointed at, and the place
// the connection details are documented. Linked rather than restated, because the config
// format is theirs to change.
const MCP_CLIENT_URL = "https://github.com/Automattic/mcp-wordpress-remote";

// ---------------------------------------------------------------------------
// Settings card shell
// ---------------------------------------------------------------------------

/**
 * A settings group, with a heading that says who the settings inside affect.
 *
 * The page had none: site-wide settings and per-browser preferences sat in one column
 * in an order nobody chose, and the only clue about which was which was a note below
 * the fold. Scope is the first thing you need to know before changing a setting on a
 * site other people use.
 */
function SetSection({
  title,
  desc,
  note,
  children,
}: {
  title: string;
  desc: string;
  /** Transient status for the whole section, e.g. the save marker. */
  note?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <section className="fp-set-section">
      <div className="fp-set-section-head">
        <h2 className="fp-set-section-title">
          {title}
          {note}
        </h2>
        <p className="fp-set-section-desc">{desc}</p>
      </div>
      {children}
    </section>
  );
}

function SetCard({
  icon,
  title,
  desc,
  danger,
  scope,
  testId,
  children,
}: {
  icon: IconName;
  title: string;
  desc: string;
  danger?: boolean;
  /** Who a change here affects. Shown as a badge, because it changes the stakes. */
  scope?: "site" | "browser";
  testId?: string;
  children: React.ReactNode;
}) {
  return (
    <div
      className={`fp-card fp-set-card${danger ? " fp-danger-card" : ""}`}
      data-testid={testId}
    >
      <div className="fp-set-card-head">
        <span
          className="fp-set-card-ic"
          style={
            danger
              ? {
                  background:
                    "color-mix(in oklch,var(--red) 12%,var(--surface))",
                  color: "var(--red)",
                }
              : undefined
          }
        >
          <Icon name={icon} size={18} />
        </span>
        <div>
          <div className={`fp-set-card-title${danger ? " fp-danger-label" : ""}`}>
            {title}
            {/* The site badge is accented because it is the one with consequences for
                other people; the browser badge is the quiet default. */}
            {scope && (
              <span
                className={`fp-badge fp-set-scope${"site" === scope ? " tone-accent" : ""}`}
              >
                {"site" === scope
                  ? __("Site-wide", "storeseeder")
                  : __("This browser", "storeseeder")}
              </span>
            )}
          </div>
          <div className="fp-set-card-desc">{desc}</div>
        </div>
      </div>
      {children}
    </div>
  );
}

// ---------------------------------------------------------------------------
// SettingsPage
// ---------------------------------------------------------------------------

export default function SettingsPage() {
  const [settings, setSettings] = useState(getSettings);
  const [saved, setSaved] = useState(false);
  const { clearStats, discardRun, totalGenerated, recentRuns } = useStats();
  const { toast } = useToast();
  const { theme, setTheme, density, setDensity } = useTheme();
  const {
    state: platformState,
    selected: platformSelected,
    active: activePlatformList,
    ambiguous: platformAmbiguous,
    setTarget: setPlatformTarget,
  } = usePlatform();

  // Who may generate. Server-held, unlike the rest of this page.
  const [access, setAccess] = useState<AccessState | null>(null);
  // Distinguished from "still loading", so a failed fetch says so instead of showing a
  // skeleton that never resolves.
  const [accessFailed, setAccessFailed] = useState(false);
  const [savingRoles, setSavingRoles] = useState(false);

  // Recipes sync state. Separate from the sample data's even though one consent record covers both
  // downloads: they are two archives, either can be present without the other, and a single spinner
  // would disable both cards while one of them worked.
  const [recipesStatus, setRecipesStatus] = useState<RecipesStatus | null>(null);
  const [recipesLoading, setRecipesLoading] = useState(true);
  const [recipesSyncing, setRecipesSyncing] = useState(false);

  // Sample data sync state
  const [syncStatus, setSyncStatus] = useState<SampleDataStatus | null>(null);
  const [statusLoading, setStatusLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [syncResult, setSyncResult] = useState<{
    ok: boolean;
    message: string;
  } | null>(null);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      const status = await fetchRecipesStatus();

      if (!cancelled) {
        setRecipesStatus(status);
        setRecipesLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  /**
   * Fetch the recipe archive.
   *
   * `force` deletes the local copy first. Without it the download writes over what is there, and a
   * recipe that dropped a file between releases would keep the old one — the same reason the sample
   * data grew a force of its own.
   */
  const handleRecipesSync = useCallback(
    async (force: boolean) => {
      setRecipesSyncing(true);

      try {
        await syncRecipes(force);
        setRecipesStatus(await fetchRecipesStatus());
        toast(
          force
            ? __("Recipes refreshed", "storeseeder")
            : __("Recipes synced", "storeseeder"),
        );
      } catch (err) {
        // The endpoint answers 403 when consent has not been given, and its message says so — passed
        // through rather than replaced, because "Could not sync" would send somebody looking for a
        // network problem when the answer is the prompt above.
        toast(
          __("Could not sync the recipes", "storeseeder"),
          err instanceof Error ? err.message : "",
        );
      } finally {
        setRecipesSyncing(false);
      }
    },
    [toast],
  );

  const nonce = window.storeseederApi?.restNonce ?? "";
  const restUrl = window.storeseederApi?.restUrl ?? "";
  // Locales come from the server, which lists exactly what the REST API accepts.
  // Options carry the code so the label never has to be mapped back to one.
  const locales = localeOptions();
  // Inlined by the server: what MCP needs depends on which plugins are active, which
  // cannot change while this page is open, so there is nothing to fetch or to skeleton.
  // Held in state all the same, because the three switches below change it.
  const [mcp, setMcp] = useState(
    window.storeseederApi?.mcp
      ? parseMcpStatus(window.storeseederApi.mcp)
      : null,
  );
  const [savingMcp, setSavingMcp] = useState(false);

  // What StoreSeeder has created, from its own ledger — the only thing the delete below is
  // ever allowed to touch.
  const [generated, setGenerated] = useState<GeneratedState | null>(null);
  const [purging, setPurging] = useState(false);
  // Two-step rather than a browser confirm(): a native dialog blocks the page and looks
  // nothing like the rest of the admin.
  const [confirmPurge, setConfirmPurge] = useState(false);
  // Two actions here had no confirmation at all, and both are irreversible: forgetting ledger
  // records leaves rows in the store with nothing that knows StoreSeeder put them there, and
  // clearing the history takes the counts and the run log with it.
  const [confirmForget, setConfirmForget] = useState(false);
  const [confirmClear, setConfirmClear] = useState(false);
  const [purgeNote, setPurgeNote] = useState("");
  const [purgeErrors, setPurgeErrors] = useState<string[]>([]);

  // Applied and stored on change. The page used to have two "Save settings" buttons
  // writing the same object, next to three cards that saved the instant you touched
  // them — so a value could be typed, left unsaved, and then written by a button in a
  // different card. These are browser preferences, so there is nothing to lose by
  // writing them immediately, and it makes one model for the whole page.
  const set = <K extends keyof typeof settings>(
    key: K,
    value: (typeof settings)[K],
  ) =>
    setSettings((s) => {
      const next = { ...s, [key]: value };
      saveSettings(next);
      setSaved(true);
      return next;
    });

  // Clear the marker a moment after the last change, rather than per keystroke.
  useEffect(() => {
    if (!saved) return;

    const t = setTimeout(() => setSaved(false), 1600);
    return () => clearTimeout(t);
  }, [saved, settings]);

  const refreshSyncStatus = useCallback(
    () =>
      fetch(`${restUrl}download-sample`, {
        headers: { "X-WP-Nonce": nonce },
      })
        .then((r) => readJsonBody(r))
        .then((body) => setSyncStatus(parseSampleDataStatus(body)))
        .catch(() => setSyncStatus(null)),
    [restUrl, nonce],
  );

  // Fetch sync status on mount
  useEffect(() => {
    void refreshSyncStatus().finally(() => setStatusLoading(false));
  }, [refreshSyncStatus]);

  // The consent modal writes the decision itself, so pick up its result rather
  // than leaving this card showing the pre-prompt state.
  useEffect(() => {
    const onChanged = () => void refreshSyncStatus();

    window.addEventListener(CONSENT_CHANGED_EVENT, onChanged);
    return () => window.removeEventListener(CONSENT_CHANGED_EVENT, onChanged);
  }, [refreshSyncStatus]);

  // Three small helpers rather than ternary chains in the markup: each of these
  // picks between three states, which reads as a branch per state.
  const syncIcon = (): IconName => {
    if (statusLoading) return "refresh";
    return syncStatus?.exists ? "check2" : "alert";
  };

  const syncSummary = (): JSX.Element => {
    if (statusLoading) {
      return (
        <>
          <SkeletonText lines={2} />
          <span className="sr-only" role="status">
            {__("Checking status…", "storeseeder")}
          </span>
        </>
      );
    }

    if (syncStatus?.exists) {
      return (
        <>
          <div style={{ fontSize: 13.5, fontWeight: 500 }}>
            {__("Sample data is synced", "storeseeder")}
          </div>
          {syncStatus.last_synced && (
            <div style={{ fontSize: 12, color: "var(--text-3)" }}>
              {sprintf(
                /* translators: %s: date string */
                __("Last updated: %s", "storeseeder"),
                formatDate(syncStatus.last_synced) ?? "",
              )}
            </div>
          )}
        </>
      );
    }

    return (
      <>
        <div style={{ fontSize: 13.5, fontWeight: 500 }}>
          {__("Sample data not found", "storeseeder")}
        </div>
        <div style={{ fontSize: 12, color: "var(--text-3)" }}>
          {__(
            "Sync to download locale-specific reference data.",
            "storeseeder",
          )}
        </div>
      </>
    );
  };

  const consentHint = (): string => {
    if ("granted" === syncStatus?.consent) {
      return __(
        "Automatic download is allowed. Revoke to stop downloading on visit.",
        "storeseeder",
      );
    }

    if ("declined" === syncStatus?.consent) {
      return __(
        "Automatic download is declined. Sync now reopens the consent prompt so you can allow it.",
        "storeseeder",
      );
    }

    return __(
      "Nothing has been downloaded yet. Sync now asks for your permission first.",
      "storeseeder",
    );
  };

  const handleSync = useCallback(
    async (force = false) => {
      // Downloading is the act that needs permission, so ask here rather than
      // from a separate control. The modal performs the download on approval.
      if (syncStatus?.consent !== "granted") {
        setSyncResult(null);
        requestConsentPrompt();
        return;
      }

      setSyncing(true);
      setSyncResult(null);
      try {
        const res = await fetch(`${restUrl}download-sample`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": nonce,
          },
          body: JSON.stringify({ force }),
        });
        const body = await readJsonBody(res);
        if (!res.ok) {
          throw new Error(bodyMessage(body, `HTTP ${res.status}`));
        }
        setSyncResult({
          ok: true,
          message: bodyMessage(body, __("Sample data synced.", "storeseeder")),
        });
        const statusRes = await fetch(`${restUrl}download-sample`, {
          headers: { "X-WP-Nonce": nonce },
        });
        if (statusRes.ok) {
          setSyncStatus(parseSampleDataStatus(await readJsonBody(statusRes)));
        }
      } catch (err) {
        setSyncResult({
          ok: false,
          message:
            err instanceof Error
              ? err.message
              : __("Sync failed.", "storeseeder"),
        });
      } finally {
        setSyncing(false);
      }
    },
    [restUrl, nonce, syncStatus?.consent],
  );

  const handleSetConsent = useCallback(
    async (granted: boolean) => {
      setSyncResult(null);
      try {
        const res = await fetch(`${restUrl}download-sample/consent`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": nonce,
          },
          body: JSON.stringify({ granted }),
        });
        const body = await readJsonBody(res);
        if (!res.ok) {
          throw new Error(bodyMessage(body, `HTTP ${res.status}`));
        }
        setSyncResult({
          ok: true,
          message: granted
            ? __("Automatic download allowed.", "storeseeder")
            : __("Automatic download declined.", "storeseeder"),
        });
        const statusRes = await fetch(`${restUrl}download-sample`, {
          headers: { "X-WP-Nonce": nonce },
        });
        if (statusRes.ok) {
          setSyncStatus(parseSampleDataStatus(await readJsonBody(statusRes)));
        }
      } catch (err) {
        setSyncResult({
          ok: false,
          message:
            err instanceof Error
              ? err.message
              : __("Update failed.", "storeseeder"),
        });
      }
    },
    [restUrl, nonce],
  );

  // Auto first, then whatever is actually loaded. A target stored for a platform that
  // has since been deactivated is kept as an option, labelled as such — dropping it
  // would silently show "Auto" while the site option still says otherwise.
  const platformOptions = [
    { id: AUTO, label: __("Auto — whichever platform is active", "storeseeder") },
    ...activePlatformList.map((p) => ({ id: p.id, label: p.label })),
  ];

  if (
    platformSelected !== AUTO &&
    !platformOptions.some((o) => o.id === platformSelected)
  ) {
    platformOptions.push({
      id: platformSelected,
      label: sprintf(
        /* translators: %s: platform id stored in the site option. */
        __("%s (not active)", "storeseeder"),
        platformSelected,
      ),
    });
  }

  const handlePlatformChange = (id: string) => {
    const next = platformOptions.find((o) => o.id === id);
    if (!next || next.id === platformSelected) return;

    void setPlatformTarget(next.id).then(() =>
      toast(
        AUTO === next.id
          ? __("Target platform set to Auto", "storeseeder")
          : sprintf(
              /* translators: %s: platform name. */
              __("Target platform set to %s", "storeseeder"),
              next.label,
            ),
      ),
    );
  };

  const resolvedPlatformLabel = platformState.resolved
    ? (activePlatformList.find((p) => p.id === platformState.resolved)?.label ??
      platformState.resolved)
    : null;

  const platformHint = () => {
    if (platformAmbiguous) {
      return __(
        "More than one platform is active, so Auto cannot decide. Pick one here, or you will be asked on each generator page.",
        "storeseeder",
      );
    }

    if (!resolvedPlatformLabel) {
      return __(
        "No supported platform is active yet. Activate one and it appears here.",
        "storeseeder",
      );
    }

    return sprintf(
      /* translators: %s: platform name Auto currently resolves to. */
      __("Auto currently resolves to %s.", "storeseeder"),
      resolvedPlatformLabel,
    );
  };

  useEffect(() => {
    void fetchAccess()
      .then(setAccess)
      .catch(() => setAccessFailed(true));
  }, []);

  const refreshGenerated = useCallback(() => {
    // Failure leaves the card showing nothing to delete, which is the safe direction: it
    // offers no button rather than a button with a made-up count on it.
    void fetchGenerated()
      .then(setGenerated)
      .catch(() => setGenerated(null));
  }, []);

  useEffect(refreshGenerated, [refreshGenerated]);

  const toggleRole = async (role: string, granted: boolean) => {
    if (!access) return;

    const next = granted
      ? [...access.allowedRoles, role]
      : access.allowedRoles.filter((r) => r !== role);

    // Optimistic, then corrected by what the server stored — it drops roles the site
    // no longer defines, so the response is the truth, not the request.
    setAccess({ ...access, allowedRoles: next });
    setSavingRoles(true);

    try {
      setAccess(await saveAllowedRoles(next));
      toast(
        granted
          ? sprintf(
              /* translators: %s: role name. */
              __("%s can now generate data", "storeseeder"),
              access.roles[role] ?? role,
            )
          : sprintf(
              /* translators: %s: role name. */
              __("%s can no longer generate data", "storeseeder"),
              access.roles[role] ?? role,
            ),
      );
    } catch {
      setAccess(await fetchAccess().catch(() => access));
      toast(__("Could not save who has access", "storeseeder"));
    } finally {
      setSavingRoles(false);
    }
  };

  /**
   * Flip one MCP switch.
   *
   * Optimistic, then replaced by what the server stored: the master switch withdraws the
   * other two, and the tool count is the server's arithmetic, not a guess made here.
   */
  const toggleMcp = async (toggle: McpToggle, value: boolean) => {
    if (!mcp) return;

    setMcp({ ...mcp, [toggle]: value });
    setSavingMcp(true);

    try {
      setMcp(await saveMcpToggle(toggle, value));
    } catch {
      setMcp(await fetchMcp().catch(() => mcp));
      toast(__("Could not save the AI tool settings", "storeseeder"));
    } finally {
      setSavingMcp(false);
    }
  };

  /** The card's headline icon: a warning only when something is actually wrong. */
  const mcpIcon = (): IconName => {
    if (!mcp) return "alert";
    if (!mcp.available) return "alert";
    if (!mcp.enabled || 0 === mcp.tools) return "info";
    return "check2";
  };

  /** One line saying what an AI client can currently do here. */
  const mcpSummary = (): string => {
    if (!mcp || !mcp.available) {
      return __("Not available on this site", "storeseeder");
    }

    if (!mcp.enabled) return __("Off — no tools are exposed", "storeseeder");

    if (0 === mcp.tools) {
      return __(
        "On, but neither kind of tool is allowed — nothing is exposed",
        "storeseeder",
      );
    }

    return sprintf(
      /* translators: 1: number of tools exposed, 2: number of generators. */
      __("Active — %1$d tools across %2$d generators", "storeseeder"),
      mcp.tools,
      mcp.abilities,
    );
  };

  /**
   * Delete every row StoreSeeder recorded creating.
   *
   * Batched by the server, so this reports progress between rounds rather than holding a
   * spinner: clearing a few thousand rows takes several requests, and a page that looks
   * frozen invites a reload halfway through.
   */
  const handlePurge = async () => {
    setConfirmPurge(false);
    setPurging(true);
    setPurgeErrors([]);
    setPurgeNote("");

    try {
      const result = await purgeGenerated("", (deleted, remaining) =>
        setPurgeNote(
          sprintf(
            /* translators: 1: rows deleted so far, 2: rows still to go. */
            __("Deleted %1$s, %2$s to go…", "storeseeder"),
            deleted.toLocaleString(),
            remaining.toLocaleString(),
          ),
        ),
      );

      // Take the deleted rows back off the dashboard. Without this a full purge left the counts
      // describing a store that no longer had any of it — "Products 180" over an empty catalogue.
      //
      // The counts, not the run history: a count describes what exists and is now wrong, while
      // "you generated 50 products at 14:32" describes what happened and is still true. The same
      // split `discardRun` makes when a recipe is undone, and the same conversion — the purge
      // answers by resource and the stats are keyed by route.
      discardRun(routeRows(result.byResource));

      setPurgeErrors(result.errors);
      setPurgeNote("");
      toast(
        sprintf(
          /* translators: %s: number of rows deleted. */
          __("Deleted %s generated rows", "storeseeder"),
          result.deleted.toLocaleString(),
        ),
      );
    } catch {
      toast(__("Could not delete the generated data", "storeseeder"));
    } finally {
      setPurging(false);
      refreshGenerated();
    }
  };

  /**
   * Drop the records without touching the store.
   *
   * For a ledger that no longer matches reality — a database restored from elsewhere, rows
   * removed by hand — where the alternative is a count that can never be cleared.
   */
  const handleForget = async () => {
    setPurging(true);

    try {
      const result = await deleteGenerated("", true);
      setPurgeErrors([]);
      toast(
        sprintf(
          /* translators: %s: number of records dropped. */
          __("Forgot %s records; the rows are untouched", "storeseeder"),
          result.forgotten.toLocaleString(),
        ),
      );
    } catch {
      toast(__("Could not clear the records", "storeseeder"));
    } finally {
      setPurging(false);
      refreshGenerated();
    }
  };

  /**
   * Whether there is any local history to clear.
   *
   * Both halves, because they empty independently: the counters can be non-zero with no runs left
   * after a trim, and a run can be recorded that incremented nothing when it failed.
   */
  const hasHistory = totalGenerated > 0 || recentRuns.length > 0;

  /**
   * What the history button says.
   *
   * Named rather than left as "Clear run history & stats" when there is nothing to clear, the same
   * way the purge button above says "No generated data to delete" — a disabled button with an
   * inviting label makes the user wonder what is broken, where one that states the reason answers
   * the question before it is asked.
   */
  const clearHistoryLabel = (): string => {
    if (!hasHistory) return __("No run history to clear", "storeseeder");

    return __("Clear run history & stats", "storeseeder");
  };

  /** What the delete button says, which depends on whether there is anything to delete. */
  const purgeButtonLabel = (): string => {
    if (purging) return __("Deleting…", "storeseeder");

    const total = generated?.total ?? 0;

    if (0 === total) return __("No generated data to delete", "storeseeder");

    return sprintf(
      /* translators: %s: number of rows. */
      __("Delete generated data (%s rows)", "storeseeder"),
      total.toLocaleString(),
    );
  };

  const handleClearData = () => {
    clearStats();
    toast(__("Run history cleared", "storeseeder"));
  };

  const handleClearSettings = () => {
    const defaults = (() => {
      try {
        localStorage.removeItem("ec_fp_settings");
      } catch {}
      return getSettings();
    })();
    setSettings(defaults);
    toast(__("Settings reset to defaults", "storeseeder"));
  };

  const formatDate = (iso: string | null) => {
    if (!iso) return null;
    try {
      return new Date(iso).toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    } catch {
      return iso;
    }
  };

  return (
    <div className="fp-page wide fp-enter">
      <PageHead
        title={__("Settings", "storeseeder")}
        description={__(
          "What StoreSeeder writes, who may write it, and how this admin looks.",
          "storeseeder",
        )}
      />

      <div className="fp-settings-col">
        <SetSection
          title={__("This site", "storeseeder")}
          desc={__("Stored on the server and shared by everyone who uses StoreSeeder here.", "storeseeder")}
        >
        {/* Target platform. Site-wide, stored server-side, and the same option the
            topbar selector writes — it belongs where someone configuring the plugin
            will look for it, not only behind a dropdown in the header. */}
        {/* `boxes`, matching the topbar platform selector — the same concept should not
            wear two icons — and leaving `database` to mean stored data, which is what the
            Sample data card below uses it for. */}
        <SetCard
          icon="store"
          testId="settings-target-platform"
          scope="site"
          title={__("Target platform", "storeseeder")}
          desc={__(
            "Where generated data is written. Applies to every user on this site.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <label className="fp-set-label" htmlFor="ss-target-platform">
                {__("Platform", "storeseeder")}
              </label>
              <p className="fp-set-hint">{platformHint()}</p>
              <FieldSelect
                id="ss-target-platform"
                value={platformSelected}
                options={platformOptions.map((o) => ({
                  value: o.id,
                  label: o.label,
                }))}
                width={320}
                onChange={handlePlatformChange}
              />
            </div>

            {activePlatformList.length > 0 && (
              <p className="fp-set-hint mb-0">
                {sprintf(
                  /* translators: %s: comma-separated platform names with versions. */
                  __("Active: %s", "storeseeder"),
                  activePlatformList
                    .map((p) => (p.version ? `${p.label} ${p.version}` : p.label))
                    .join(", "),
                )}
              </p>
            )}
          </div>
        </SetCard>

        {/* Who may generate. Server-held and site-wide, unlike everything below it,
            and the only setting on this page with a security consequence — generated
            rows go straight into the store's own tables. */}
        {!access && !accessFailed && (
          <SetCard
            icon="users"
            scope="site"
            testId="settings-access-loading"
            title={__("Who can generate data", "storeseeder")}
            desc={__(
              "Administrators always can. Grant other roles here — the same access covers the admin screen, the REST API, and the AI tools.",
              "storeseeder",
            )}
          >
            <div className="fp-skel-text" aria-hidden="true">
              <Skeleton height={34} />
              <Skeleton height={34} />
              <Skeleton height={34} width="72%" />
            </div>
            <span className="sr-only" role="status">
              {__("Loading roles…", "storeseeder")}
            </span>
          </SetCard>
        )}

        {accessFailed && (
          <SetCard
            icon="users"
            scope="site"
            title={__("Who can generate data", "storeseeder")}
            desc={__(
              "Administrators always can. Grant other roles here — the same access covers the admin screen, the REST API, and the AI tools.",
              "storeseeder",
            )}
          >
            <p className="fp-set-hint mb-0" style={{ color: "var(--red)" }}>
              {__(
                "Could not load who has access. Reload the page to try again.",
                "storeseeder",
              )}
            </p>
          </SetCard>
        )}

        {access && (
          <SetCard
            icon="users"
            testId="settings-access"
            scope="site"
            title={__("Who can generate data", "storeseeder")}
            desc={__(
              "Administrators always can. Grant other roles here — the same access covers the admin screen, the REST API, and the AI tools.",
              "storeseeder",
            )}
          >
            <div>
              {Object.keys(access.roles).length === 0 && (
                <p className="fp-set-hint mb-0">
                  {__(
                    "This site defines no roles other than Administrator.",
                    "storeseeder",
                  )}
                </p>
              )}

              {Object.entries(access.roles).map(([slug, name]) => (
                <div className="fp-set-field full" key={slug}>
                  <Toggle
                    checked={access.allowedRoles.includes(slug)}
                    disabled={!access.canManage || savingRoles}
                    onChange={(granted) => void toggleRole(slug, granted)}
                    testId={`role-${slug}`}
                    label={name}
                    hint={
                      access.allowedRoles.includes(slug)
                        ? __(
                            "Can generate data, and can write it into the live store.",
                            "storeseeder",
                          )
                        : __("No access.", "storeseeder")
                    }
                  />
                </div>
              ))}

              {!access.canManage && (
                <p className="fp-set-hint mb-0">
                  {__(
                    "Only administrators can change who has access, so that a role granted here cannot widen it further.",
                    "storeseeder",
                  )}
                </p>
              )}

              {access.filtered && (
                <p className="fp-set-hint mb-0">
                  {sprintf(
                    /* translators: %s: capability name, e.g. edit_shop_orders. */
                    __(
                      "Code on this site also grants access through the %s capability, via the storeseeder_capability filter.",
                      "storeseeder",
                    ),
                    access.capability,
                  )}
                </p>
              )}
            </div>
          </SetCard>
        )}

        {/* MCP. Site-wide, since it depends on which plugins are active — and reported
            rather than hidden when unavailable: the two things it needs are not StoreSeeder's
            to install, so naming them is the only useful thing this card can do. */}
        {mcp && (
          <SetCard
            icon="sparkles"
            scope="site"
            testId="settings-mcp"
            title={__("AI tools (MCP)", "storeseeder")}
            desc={__(
              "Exposes the generators as tools an AI client can call, so test data can be asked for in words rather than clicked.",
              "storeseeder",
            )}
          >
            <div>
              <div className="fp-set-sync">
                <span className="fp-set-sync-ic">
                  <Icon name={mcpIcon()} size={19} />
                </span>
                <div>
                  <div style={{ fontSize: 13.5, fontWeight: 500 }}>
                    {mcpSummary()}
                  </div>
                  {mcp.available && mcp.enabled && 0 < mcp.tools && (
                    <div style={{ fontSize: 12, color: "var(--text-3)" }}>
                      <code>{mcp.route}</code>
                    </div>
                  )}
                </div>
              </div>

              {/* Three switches, in the order the risk grows: whether there is an AI
                  surface at all, whether an agent may look, whether it may write. Two
                  tools per generator is what makes that last one a real boundary — with
                  generation off an agent can still answer "what would 50 orders look
                  like?", and cannot put them in the store. */}
              {mcp.available && (
                <div style={{ marginTop: 4 }}>
                  <div className="fp-set-field full">
                    <Toggle
                      checked={mcp.enabled}
                      disabled={!mcp.can_manage || savingMcp}
                      onChange={(v) => void toggleMcp("enabled", v)}
                      testId="mcp-enabled"
                      label={__("Enable AI tools", "storeseeder")}
                      hint={__(
                        "Serves the MCP endpoint. Off means no tools at all, and a client can no longer connect.",
                        "storeseeder",
                      )}
                    />
                  </div>

                  <div className="fp-set-field full">
                    <Toggle
                      checked={mcp.enabled && mcp.preview}
                      disabled={!mcp.can_manage || !mcp.enabled || savingMcp}
                      onChange={(v) => void toggleMcp("preview", v)}
                      testId="mcp-preview"
                      label={sprintf(
                        /* translators: %d: number of generators. */
                        __("Allow preview tools (%d)", "storeseeder"),
                        mcp.abilities,
                      )}
                      hint={__(
                        "Read-only. Shows the rows a run would create, and writes nothing.",
                        "storeseeder",
                      )}
                    />
                  </div>

                  <div className="fp-set-field full">
                    <Toggle
                      checked={mcp.enabled && mcp.generate}
                      disabled={!mcp.can_manage || !mcp.enabled || savingMcp}
                      onChange={(v) => void toggleMcp("generate", v)}
                      testId="mcp-generate"
                      label={sprintf(
                        /* translators: %d: number of generators. */
                        __("Allow generating (%d, writes rows)", "storeseeder"),
                        mcp.abilities,
                      )}
                      hint={__(
                        "Lets an agent insert data into the store. Off leaves it able to preview only.",
                        "storeseeder",
                      )}
                    />
                  </div>

                  {!mcp.can_manage && (
                    <p className="fp-set-hint">
                      {__(
                        "Only administrators can change what an AI client may do.",
                        "storeseeder",
                      )}
                    </p>
                  )}
                </div>
              )}

              {/* One line per missing dependency, because "install the Abilities API" and
                  "install mcp-adapter" are different jobs and a combined message sends the
                  reader looking for the wrong one. */}
              {!mcp.available && (
                <ul className="fp-set-reqs">
                  <li>
                    <Icon
                      name={mcp.abilities_api ? "check2" : "x"}
                      size={14}
                      className={mcp.abilities_api ? "is-met" : "is-missing"}
                    />
                    {__(
                      "WordPress Abilities API — bundled with WordPress 6.9 and later, or installable as a plugin",
                      "storeseeder",
                    )}
                  </li>
                  <li>
                    <Icon
                      name={mcp.adapter ? "check2" : "x"}
                      size={14}
                      className={mcp.adapter ? "is-met" : "is-missing"}
                    />
                    {__(
                      "The mcp-adapter plugin, which serves the tools to a client",
                      "storeseeder",
                    )}
                  </li>
                </ul>
              )}

              <p className="fp-set-hint">
                {__(
                  "Optional. Nothing else changes when it is absent — the admin, the REST API and WP-CLI all work the same. The same tools are also reachable through mcp-adapter's own default server, under whatever these switches allow.",
                  "storeseeder",
                )}
              </p>

              {/* A client needs a proxy and an application password, neither of which this
                  page can hand out. Automattic's package documents both, and is where the
                  config format is kept up to date. */}
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <a href={MCP_CLIENT_URL} target="_blank" rel="noopener noreferrer">
                  <Button variant="outline" size="sm" icon="external" type="button">
                    {__("How to connect a client", "storeseeder")}
                  </Button>
                </a>
                {/* What the two tool families are for and what each switch withdraws. The three
                    switches above are easy to operate and hard to reason about from the page alone. */}
                <a href={DOCS.mcp} target="_blank" rel="noopener noreferrer">
                  <Button variant="ghost" size="sm" icon="book" type="button">
                    {__("Read the AI tools guide", "storeseeder")}
                  </Button>
                </a>
              </div>
            </div>
          </SetCard>
        )}

        {/* Sample data */}
        <SetCard
          icon="database"
          scope="site"
          title={__("Sample data", "storeseeder")}
          desc={__(
            "Locale-specific reference data used by generators to produce realistic output.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-sync">
              <span className="fp-set-sync-ic">
                <Icon name={syncIcon()} size={19} />
              </span>
              <div>
                {syncSummary()}
              </div>
            </div>

            {syncResult && (
              <p
                className="fp-set-hint"
                style={{
                  marginBottom: 12,
                  color: syncResult.ok ? "var(--green)" : "var(--red)",
                }}
              >
                {syncResult.message}
              </p>
            )}

            <p className="fp-set-hint" style={{ marginBottom: 12 }}>
              {consentHint()}
            </p>

            <div style={{ display: "flex", gap: 8 }}>
              <Button
                variant="primary"
                icon="refresh"
                onClick={() => void handleSync(false)}
                disabled={syncing}
              >
                {syncing
                  ? __("Syncing…", "storeseeder")
                  : __("Sync now", "storeseeder")}
              </Button>
              <Button
                variant="outline"
                icon="refresh"
                onClick={() => void handleSync(true)}
                disabled={syncing}
              >
                {__("Force re-sync", "storeseeder")}
              </Button>
              {syncStatus?.consent === "granted" && (
                <Button
                  variant="outline"
                  icon="x"
                  onClick={() => void handleSetConsent(false)}
                  disabled={syncing}
                >
                  {__("Revoke", "storeseeder")}
                </Button>
              )}
            </div>

            <div style={{ marginTop: 14, display: "flex", gap: 8, flexWrap: "wrap" }}>
              {/* The server reports the repository, which storeseeder_sample_data_source
                  can change; the constant is only a fallback for a failed status call. */}
              <a
                href={syncStatus?.repo_url || SAMPLE_DATA_REPO_URL}
                target="_blank"
                rel="noopener noreferrer"
              >
                <Button variant="ghost" size="sm" icon="external" type="button">
                  {__(
                    "View sample data repository",
                    "storeseeder",
                  )}
                </Button>
              </a>
              {/* Exactly what is requested, when, and what declining costs. The consent prompt states
                  it once at the moment of asking; this is where somebody comes back to check. */}
              <a href={DOCS.externalServices} target="_blank" rel="noopener noreferrer">
                <Button variant="ghost" size="sm" icon="book" type="button">
                  {__("What gets downloaded", "storeseeder")}
                </Button>
              </a>
            </div>
          </div>
        </SetCard>

        {/* Recipes. Its own card rather than a line in the sample-data one: they are two archives,
            downloaded separately, and either can be present without the other — a single control would
            make "synced" ambiguous about which. */}
        <SetCard
          icon="store"
          scope="site"
          title={__( "Recipes", "storeseeder" )}
          desc={__(
            "Ready-made shops — a corner grocer, a fashion boutique, a home & garden store — built across nine resources in one click.",
            "storeseeder",
          )}
          testId="settings-recipes"
        >
          <div>
            <div className="fp-set-sync">
              <span className="fp-set-sync-ic">
                <Icon
                  name={recipesStatus?.exists ? "check" : "alert"}
                  size={19}
                />
              </span>
              <div>
                {recipesLoading ? (
                  <SkeletonText lines={2} />
                ) : (
                  <>
                    <div className="fp-set-sync-title">
                      {recipesStatus?.exists
                        ? sprintf(
                            /* translators: %s: number of recipes available. */
                            _n(
                              "%s recipe available",
                              "%s recipes available",
                              recipesStatus.recipes,
                              "storeseeder",
                            ),
                            String( recipesStatus.recipes ),
                          )
                        : __( "No recipes downloaded yet", "storeseeder" )}
                    </div>
                    <div className="fp-set-sync-sub">
                      {recipesStatus?.last_synced
                        ? sprintf(
                            /* translators: %s: date and time of the last sync. */
                            __( "Last updated: %s", "storeseeder" ),
                            new Date( recipesStatus.last_synced ).toLocaleString(),
                          )
                        : __(
                            "About 90 KB, fetched once. The Recipes page works offline afterwards.",
                            "storeseeder",
                          )}
                    </div>
                  </>
                )}
              </div>
            </div>

            {/* Named, not swallowed. A fetch that dropped half the archive would otherwise present as
                a shorter list of recipes, and a shorter list looks like a decision somebody made. */}
            {undefined !== recipesStatus?.incomplete && recipesStatus.incomplete.length > 0 && (
              <p className="fp-set-hint" style={{ marginBottom: 12, color: "var(--red)" }}>
                {sprintf(
                  /* translators: %s: comma-separated recipe ids. */
                  __(
                    "The index lists these but they are not on disk: %s. Force a re-sync.",
                    "storeseeder",
                  ),
                  recipesStatus.incomplete.join( ", " ),
                )}
              </p>
            )}

            <p className="fp-set-hint" style={{ marginBottom: 12 }}>
              {"granted" === recipesStatus?.consent
                ? __(
                    "Downloads are allowed. Recipes and sample data share one consent record, so revoking it above stops both.",
                    "storeseeder",
                  )
                : __(
                    "Needs the same consent the sample data uses. Accept it above and this will download.",
                    "storeseeder",
                  )}
            </p>

            <div style={{ display: "flex", gap: 8 }}>
              <Button
                variant="primary"
                icon="refresh"
                type="button"
                onClick={() => void handleRecipesSync( false )}
                disabled={recipesSyncing}
                data-testid="recipes-sync"
              >
                {recipesSyncing
                  ? __( "Syncing…", "storeseeder" )
                  : __( "Sync now", "storeseeder" )}
              </Button>
              <Button
                variant="outline"
                icon="refresh"
                type="button"
                onClick={() => void handleRecipesSync( true )}
                disabled={recipesSyncing}
              >
                {__( "Force re-sync", "storeseeder" )}
              </Button>
            </div>

            <div style={{ marginTop: 14, display: "flex", gap: 8, flexWrap: "wrap" }}>
              <a
                href={recipesStatus?.repo_url || RECIPES_REPO_URL}
                target="_blank"
                rel="noopener noreferrer"
              >
                <Button variant="ghost" size="sm" icon="external" type="button">
                  {__( "View recipes repository", "storeseeder" )}
                </Button>
              </a>
              <a href={DOCS.recipes} target="_blank" rel="noopener noreferrer">
                <Button variant="ghost" size="sm" icon="book" type="button">
                  {__( "How recipes work", "storeseeder" )}
                </Button>
              </a>
            </div>
          </div>
        </SetCard>

        </SetSection>

        <SetSection
          title={__("Your preferences", "storeseeder")}
          desc={__("Stored in this browser, for you alone. Nothing here changes what anyone else sees.", "storeseeder")}
          note={
            saved ? (
              <span className="fp-set-saved" data-testid="settings-saved">
                <Icon name="check2" size={13} />
                {__("Saved", "storeseeder")}
              </span>
            ) : null
          }
        >
        {/* Generation defaults */}
        <SetCard
          icon="sliders"
          scope="browser"
          title={__("Generation defaults", "storeseeder")}
          desc={__(
            "Pre-fill values on every generator page.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <label className="fp-set-label" htmlFor="ss-default-count">
                {__("Default count", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "Number of items pre-filled on every generator page.",
                  "storeseeder",
                )}
              </p>
              <NumberField
                id="ss-default-count"
                value={settings.defaultCount}
                width={130}
                onChange={(v) =>
                  set("defaultCount", Math.max(1, parseInt(v, 10) || 1))
                }
              />
            </div>

            <div className="fp-set-field">
              <label className="fp-set-label" htmlFor="ss-default-locale">
                {__("Default locale", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {sprintf(
                  /* translators: %d: number of available locales. */
                  __(
                    "Locale used when generating data — %d available, all accepted by the REST API.",
                    "storeseeder",
                  ),
                  locales.length,
                )}
              </p>
              <FieldSelect
                id="ss-default-locale"
                value={settings.defaultLocale}
                options={locales.map((l) => ({
                  value: l.code,
                  label: l.label,
                }))}
                width={320}
                onChange={(code) => set("defaultLocale", code || DEFAULT_LOCALE)}
              />
            </div>

            <div className="fp-set-field">
              <label className="fp-set-label" htmlFor="ss-default-seed">
                {__("Default seed", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "Fixed seed for reproducible runs. Leave blank for random output.",
                  "storeseeder",
                )}
              </p>
              <div style={{ maxWidth: 220 }}>
                <TextField
                  id="ss-default-seed"
                  value={settings.defaultSeed}
                  ph={__("random (leave blank)", "storeseeder")}
                  onChange={(v) => set("defaultSeed", v)}
                />
              </div>
            </div>

            <div className="fp-set-field full">
              <Toggle
                checked={settings.defaultIncludeMeta}
                onChange={(v) => set("defaultIncludeMeta", v)}
                label={__(
                  "Include metadata by default",
                  "storeseeder",
                )}
                hint={__(
                  "Pre-check the Include Metadata toggle on every generator.",
                  "storeseeder",
                )}
              />
            </div>
          </div>
        </SetCard>

        {/* Appearance. Theme and density only — accent and per-token colors stay in
            Tweaks, which is linked below rather than reproduced here. Both read the
            same ThemeProvider, so a change made in one shows in the other. */}
        <SetCard
          icon="palette"
          testId="settings-appearance"
          scope="browser"
          title={__("Appearance", "storeseeder")}
          desc={__(
            "How the admin looks. Saved in this browser, per user.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <span className="fp-set-label">{__("Theme", "storeseeder")}</span>
              <p className="fp-set-hint">
                {__(
                  "Independent of the WordPress admin colour scheme.",
                  "storeseeder",
                )}
              </p>
              <Seg<Theme>
                value={theme}
                onChange={setTheme}
                ariaLabel={__("Theme", "storeseeder")}
                options={[
                  { v: "light", label: __("Light", "storeseeder"), ic: "sun" },
                  { v: "dark", label: __("Dark", "storeseeder"), ic: "moon" },
                ]}
              />
            </div>

            <div className="fp-set-field">
              <span className="fp-set-label">{__("Density", "storeseeder")}</span>
              <p className="fp-set-hint">
                {__(
                  "Compact tightens row heights and padding across every page.",
                  "storeseeder",
                )}
              </p>
              <Seg<Density>
                value={density}
                onChange={setDensity}
                ariaLabel={__("Density", "storeseeder")}
                options={[
                  {
                    v: "comfortable",
                    label: __("Comfortable", "storeseeder"),
                  },
                  { v: "compact", label: __("Compact", "storeseeder") },
                ]}
              />
            </div>

            <Button
              variant="outline"
              size="sm"
              icon="sliders"
              onClick={requestTweaksPanel}
            >
              {__("Accent & custom colours…", "storeseeder")}
            </Button>
          </div>
        </SetCard>

        {/* Run history */}
        <SetCard
          icon="history"
          scope="browser"
          title={__("Run history", "storeseeder")}
          desc={__(
            "Control how much history is retained.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <label className="fp-set-label" htmlFor="ss-max-runs">
                {__("Max runs per generator", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "How many recent runs to store in history per generator type.",
                  "storeseeder",
                )}
              </p>
              <NumberField
                id="ss-max-runs"
                value={settings.maxRunsPerGenerator}
                width={130}
                onChange={(v) =>
                  set(
                    "maxRunsPerGenerator",
                    Math.min(50, Math.max(5, parseInt(v, 10) || 10)),
                  )
                }
              />
            </div>          </div>
        </SetCard>
        </SetSection>

        <SetSection
          title={__("Plugin", "storeseeder")}
          desc={__("Version, links, and the actions that cannot be undone.", "storeseeder")}
        >
        {/* About */}
        <SetCard
          icon="info"
          title={__("About", "storeseeder")}
          desc={sprintf(
            /* translators: %s: version number */
            __("StoreSeeder · Version %s", "storeseeder"),
            PLUGIN_VERSION,
          )}
        >
          <div style={{ display: "flex", gap: 8 }}>
            <a href={GITHUB_URL} target="_blank" rel="noopener noreferrer">
              <Button variant="outline" size="sm" icon="github" type="button">
                {__("GitHub", "storeseeder")}
              </Button>
            </a>
            <a href={DOCS_URL} target="_blank" rel="noopener noreferrer">
              <Button variant="outline" size="sm" icon="book" type="button">
                {__("Documentation", "storeseeder")}
              </Button>
            </a>
            <a href={ISSUES_URL} target="_blank" rel="noopener noreferrer">
              <Button variant="outline" size="sm" icon="external" type="button">
                {__("Support", "storeseeder")}
              </Button>
            </a>
          </div>
        </SetCard>

        {/* Danger zone */}
        <SetCard
          icon="trash"
          title={__("Danger zone", "storeseeder")}
          desc={__("These actions cannot be undone.", "storeseeder")}
          danger
        >
          {/* Before the buttons, not after. Four irreversible actions whose differences matter —
              deleting rows, forgetting the record of rows, clearing local history, resetting
              preferences — and the page has room for one line about each. */}
          <div style={{ marginBottom: 14 }}>
            <a href={DOCS.cleanup} target="_blank" rel="noopener noreferrer">
              <Button variant="ghost" size="sm" icon="book" type="button">
                {__("What each of these removes", "storeseeder")}
              </Button>
            </a>
          </div>

          <div>
            {/* First, because it is the only action here that touches the store. The count
                comes from the plugin's own ledger of rows it wrote, so this deletes what
                StoreSeeder created and nothing that resembles it. */}
            <div className="fp-danger-act" data-testid="danger-generated">
              <div>
                <Button
                  variant="danger"
                  icon="trash"
                  onClick={() => setConfirmPurge(true)}
                  disabled={purging || 0 === (generated?.total ?? 0)}
                  data-testid="delete-generated"
                >
                  {purgeButtonLabel()}
                </Button>
              </div>

              <p className="fp-set-hint" style={{ marginTop: 7 }}>
                {0 === (generated?.total ?? 0)
                  ? __(
                      "Nothing recorded yet. Rows created by StoreSeeder are logged so they can be removed later; anything generated before this version was released is not in that log.",
                      "storeseeder",
                    )
                  : __(
                      "Permanently removes the products, orders, customers and other rows StoreSeeder created, along with what hangs off them. Only rows StoreSeeder recorded creating are touched — your own data is never matched on.",
                      "storeseeder",
                    )}
              </p>

              {/* The breakdown is what makes the number checkable before it is acted on. */}
              {generated && 0 < generated.resources.length && (
                <ul className="fp-set-reqs" data-testid="generated-breakdown">
                  {generated.resources.map((row) => (
                    <li key={row.resource}>
                      <Icon name="list" size={14} />
                      {sprintf(
                        /* translators: 1: resource name, e.g. Products. 2: number of rows. */
                        __("%1$s — %2$s", "storeseeder"),
                        resourceLabel(row.resource),
                        row.count.toLocaleString(),
                      )}
                    </li>
                  ))}
                </ul>
              )}

              {purgeNote && (
                <p className="fp-set-hint" aria-live="polite">
                  {purgeNote}
                </p>
              )}

              {/* A refusal names its reason once, not once per row, and offers the only
                  thing that can clear a count that will never delete: forgetting it. */}
              {0 < purgeErrors.length && (
                <div style={{ marginTop: 4 }}>
                  {purgeErrors.map((error) => (
                    <p
                      className="fp-set-hint"
                      style={{ color: "var(--red)" }}
                      key={error}
                    >
                      {error}
                    </p>
                  ))}
                  <Button
                    variant="outline"
                    size="sm"
                    icon="x"
                    type="button"
                    onClick={() => setConfirmForget(true)}
                    disabled={purging}
                  >
                    {__("Forget the remaining records", "storeseeder")}
                  </Button>
                </div>
              )}
            </div>

            <div className="fp-danger-act">
              <div>
                <Button
                  variant="danger"
                  icon="trash"
                  type="button"
                  disabled={!hasHistory}
                  onClick={() => setConfirmClear(true)}
                  data-testid="clear-history"
                >
                  {clearHistoryLabel()}
                </Button>
              </div>
              <p className="fp-set-hint" style={{ marginTop: 7 }}>
                {hasHistory
                  ? __(
                      "Removes locally stored generation stats and run history. Does not delete data in your database.",
                      "storeseeder",
                    )
                  : __(
                      "Nothing recorded locally. The dashboard counts and the recent-activity list are stored in this browser, so they are already empty here — note that another browser may still have its own.",
                      "storeseeder",
                    )}
              </p>
            </div>
            <div className="fp-danger-act mb-0">
              <div>
                <Button
                  variant="danger"
                  icon="refresh"
                  onClick={handleClearSettings}
                >
                  {__(
                    "Reset settings to defaults",
                    "storeseeder",
                  )}
                </Button>
              </div>
              <p className="fp-set-hint mb-0" style={{ marginTop: 7 }}>
                {__(
                  "Resets all settings to their default values.",
                  "storeseeder",
                )}
              </p>
            </div>
          </div>
        </SetCard>
        </SetSection>

      </div>

      {/*
        All three at the end rather than beside their buttons: a dialog is a layer over the page,
        and nesting one inside a card that scrolls means it inherits that card's clipping.
      */}
      {confirmPurge && (
        <ConfirmDialog
          testId="confirm-purge"
          title={__("Delete the generated data?", "storeseeder")}
          body={
            <>
              <p>
                {sprintf(
                  /* translators: %s: number of rows. */
                  __(
                    "This permanently removes the %s rows StoreSeeder recorded creating, along with what hangs off them.",
                    "storeseeder",
                  ),
                  (generated?.total ?? 0).toLocaleString(),
                )}
              </p>
              <p>
                {__(
                  "Your own data is never matched on. Only rows in StoreSeeder's own ledger are touched, so a catalogue restored from production is safe.",
                  "storeseeder",
                )}
              </p>
            </>
          }
          confirmLabel={sprintf(
            /* translators: %s: number of rows. */
            __("Yes, delete %s rows", "storeseeder"),
            (generated?.total ?? 0).toLocaleString(),
          )}
          busyLabel={__("Deleting…", "storeseeder")}
          busy={purging}
          onCancel={() => setConfirmPurge(false)}
          onConfirm={() => void handlePurge()}
        />
      )}

      {confirmForget && (
        <ConfirmDialog
          testId="confirm-forget"
          icon="x"
          title={__("Forget the remaining records?", "storeseeder")}
          body={
            <>
              <p>
                {__(
                  "This drops StoreSeeder's record of those rows without deleting them. They stay in your store.",
                  "storeseeder",
                )}
              </p>
              <p>
                {__(
                  "Afterwards nothing knows StoreSeeder created them, so they can never be removed automatically — you would have to find and delete them yourself.",
                  "storeseeder",
                )}
              </p>
            </>
          }
          confirmLabel={__("Yes, forget them", "storeseeder")}
          busy={purging}
          onCancel={() => setConfirmForget(false)}
          onConfirm={() => {
            setConfirmForget(false);
            void handleForget();
          }}
        />
      )}

      {confirmClear && (
        <ConfirmDialog
          testId="confirm-clear-history"
          title={__("Clear the run history and stats?", "storeseeder")}
          body={
            <>
              <p>
                {__(
                  "This clears the dashboard counts, the sparklines and the recent-activity list.",
                  "storeseeder",
                )}
              </p>
              <p>
                {__(
                  "Nothing is deleted from your store — but StoreSeeder's own ledger is separate from this, so the Delete option above keeps working afterwards.",
                  "storeseeder",
                )}
              </p>
            </>
          }
          confirmLabel={__("Yes, clear the history", "storeseeder")}
          onCancel={() => setConfirmClear(false)}
          onConfirm={() => {
            setConfirmClear(false);
            handleClearData();
          }}
        />
      )}
    </div>
  );
}
