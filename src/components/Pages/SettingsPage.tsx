import React from "react";
import { useState, useCallback, useEffect } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

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
import { requestTweaksPanel } from "@/lib/events";
import { DEFAULT_LOCALE, localeOptions } from "@/lib/locales";
import { AUTO } from "@/lib/platform";
import { usePlatform } from "@/providers/PlatformProvider";
import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";
import { useTheme, type Density, type Theme } from "@/theme/useTheme";

// Localized from STORESEEDER_VERSION; the fallback only shows if the script
// data is missing, which would mean the admin app failed to enqueue properly.
const PLUGIN_VERSION = window.storeseederApi?.version || "—";
const GITHUB_URL = "https://github.com/mralaminahamed/storeseeder";
const SAMPLE_DATA_REPO_URL =
  "https://github.com/mralaminahamed/storeseeder-sample-data-fluent-cart";
const SUPPORT_URL =
  "https://github.com/mralaminahamed/storeseeder/issues";
const DOCS_URL =
  "https://github.com/mralaminahamed/storeseeder#readme";

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
  const { clearStats } = useStats();
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

  // Sample data sync state
  const [syncStatus, setSyncStatus] = useState<SampleDataStatus | null>(null);
  const [statusLoading, setStatusLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [syncResult, setSyncResult] = useState<{
    ok: boolean;
    message: string;
  } | null>(null);

  const nonce = window.storeseederApi?.restNonce ?? "";
  const restUrl = window.storeseederApi?.restUrl ?? "";
  // Locales come from the server, which lists exactly what the REST API accepts.
  // Options carry the code so the label never has to be mapped back to one.
  const locales = localeOptions();

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
    <div className="fp-page fp-enter">
      <div className="fp-page-head">
        <div>
          <h1 className="fp-h1">{__("Settings", "storeseeder")}</h1>
          <p className="fp-sub">
            {__(
              "What StoreSeeder writes, who may write it, and how this admin looks.",
              "storeseeder",
            )}
          </p>
        </div>
      </div>

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

            <div style={{ marginTop: 14 }}>
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
            <a href={SUPPORT_URL} target="_blank" rel="noopener noreferrer">
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
          <div>
            <div className="fp-danger-act">
              <div>
                <Button variant="danger" icon="trash" onClick={handleClearData}>
                  {__(
                    "Clear run history & stats",
                    "storeseeder",
                  )}
                </Button>
              </div>
              <p className="fp-set-hint" style={{ marginTop: 7 }}>
                {__(
                  "Removes locally stored generation stats and run history. Does not delete data in your database.",
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
    </div>
  );
}
