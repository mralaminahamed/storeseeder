import React from "react";
import { useState, useCallback, useEffect } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import { Button } from "@/admin/components/ui/button";
import { Toggle } from "@/admin/components/generator/fields/Toggle";
import { NumberField } from "@/admin/components/generator/fields/NumberField";
import { TextField } from "@/admin/components/generator/fields/TextField";
import { FieldSelect } from "@/admin/components/generator/fields/FieldSelect";
import { Icon } from "@/admin/lib/icons";
import {
  CONSENT_CHANGED_EVENT,
  requestConsentPrompt,
} from "@/admin/lib/consent";
import { getSettings, saveSettings } from "@/admin/lib/settings";
import { useStats } from "@/admin/providers/StatsProvider";
import { useToast } from "@/admin/providers/ToastProvider";

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

interface SyncStatus {
  exists: boolean;
  last_synced: string | null;
  repo_url: string;
  consent?: "granted" | "declined" | null;
}

// ---------------------------------------------------------------------------
// Settings card shell
// ---------------------------------------------------------------------------

function SetCard({
  icon,
  title,
  desc,
  danger,
  children,
}: {
  icon: string;
  title: string;
  desc: string;
  danger?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className={`fp-card fp-set-card${danger ? " fp-danger-card" : ""}`}>
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

  // Sample data sync state
  const [syncStatus, setSyncStatus] = useState<SyncStatus | null>(null);
  const [statusLoading, setStatusLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [syncResult, setSyncResult] = useState<{
    ok: boolean;
    message: string;
  } | null>(null);

  const nonce = window.storeseederApi?.restNonce ?? "";
  const restUrl = window.storeseederApi?.restUrl ?? "";
  const allLocales = window.storeseederApi?.locale?.allLocales ?? {};

  // Map between faker code (stored) and human label (displayed).
  const codeToLabel = (code: string) => allLocales[code] ?? code;
  const labelToCode = (label: string) =>
    Object.keys(allLocales).find((c) => allLocales[c] === label) ?? label;
  const localeLabels = Object.values(allLocales).filter(Boolean);

  const set = <K extends keyof typeof settings>(
    key: K,
    value: (typeof settings)[K],
  ) => setSettings((s) => ({ ...s, [key]: value }));

  const refreshSyncStatus = useCallback(
    () =>
      fetch(`${restUrl}download-sample`, {
        headers: { "X-WP-Nonce": nonce },
      })
        .then((r) => r.json())
        .then((data: SyncStatus) => setSyncStatus(data))
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

  const handleSave = () => {
    saveSettings(settings);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
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
        const json = await res.json();
        if (!res.ok) throw new Error(json?.message ?? `HTTP ${res.status}`);
        setSyncResult({ ok: true, message: json.message });
        const statusRes = await fetch(`${restUrl}download-sample`, {
          headers: { "X-WP-Nonce": nonce },
        });
        if (statusRes.ok) setSyncStatus(await statusRes.json());
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
        const json = await res.json();
        if (!res.ok) throw new Error(json?.message ?? `HTTP ${res.status}`);
        setSyncResult({
          ok: true,
          message: granted
            ? __("Automatic download allowed.", "storeseeder")
            : __("Automatic download declined.", "storeseeder"),
        });
        const statusRes = await fetch(`${restUrl}download-sample`, {
          headers: { "X-WP-Nonce": nonce },
        });
        if (statusRes.ok) setSyncStatus(await statusRes.json());
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
              "Configure default behaviour for data generation.",
              "storeseeder",
            )}
          </p>
        </div>
      </div>

      <div className="fp-settings-col">
        {/* Generation defaults */}
        <SetCard
          icon="sliders"
          title={__("Generation defaults", "storeseeder")}
          desc={__(
            "Pre-fill values on every generator page.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <label className="fp-set-label">
                {__("Default count", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "Number of items pre-filled on every generator page.",
                  "storeseeder",
                )}
              </p>
              <NumberField
                value={settings.defaultCount}
                width={130}
                onChange={(v) =>
                  set("defaultCount", Math.max(1, parseInt(v, 10) || 1))
                }
              />
            </div>

            <div className="fp-set-field">
              <label className="fp-set-label">
                {__("Default locale", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "Faker locale used when generating data.",
                  "storeseeder",
                )}
              </p>
              <FieldSelect
                value={codeToLabel(settings.defaultLocale)}
                options={localeLabels}
                width={320}
                onChange={(label) => set("defaultLocale", labelToCode(label))}
              />
            </div>

            <div className="fp-set-field">
              <label className="fp-set-label">
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

            <Button variant="primary" icon="check" onClick={handleSave}>
              {saved
                ? __("Saved!", "storeseeder")
                : __("Save settings", "storeseeder")}
            </Button>
          </div>
        </SetCard>

        {/* Run history */}
        <SetCard
          icon="history"
          title={__("Run history", "storeseeder")}
          desc={__(
            "Control how much history is retained.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-field">
              <label className="fp-set-label">
                {__("Max runs per generator", "storeseeder")}
              </label>
              <p className="fp-set-hint">
                {__(
                  "How many recent runs to store in history per generator type.",
                  "storeseeder",
                )}
              </p>
              <NumberField
                value={settings.maxRunsPerGenerator}
                width={130}
                onChange={(v) =>
                  set(
                    "maxRunsPerGenerator",
                    Math.min(50, Math.max(5, parseInt(v, 10) || 10)),
                  )
                }
              />
            </div>
            <Button variant="primary" icon="check" onClick={handleSave}>
              {saved
                ? __("Saved!", "storeseeder")
                : __("Save settings", "storeseeder")}
            </Button>
          </div>
        </SetCard>

        {/* Sample data */}
        <SetCard
          icon="database"
          title={__("Sample data", "storeseeder")}
          desc={__(
            "Locale-specific reference data used by generators to produce realistic output.",
            "storeseeder",
          )}
        >
          <div>
            <div className="fp-set-sync">
              <span className="fp-set-sync-ic">
                <Icon
                  name={
                    statusLoading
                      ? "refresh"
                      : syncStatus?.exists
                        ? "check2"
                        : "alert"
                  }
                  size={19}
                />
              </span>
              <div>
                {statusLoading ? (
                  <div style={{ fontSize: 13.5, fontWeight: 550 }}>
                    {__("Checking status…", "storeseeder")}
                  </div>
                ) : syncStatus?.exists ? (
                  <>
                    <div style={{ fontSize: 13.5, fontWeight: 550 }}>
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
                ) : (
                  <>
                    <div style={{ fontSize: 13.5, fontWeight: 550 }}>
                      {__("Sample data not found", "storeseeder")}
                    </div>
                    <div style={{ fontSize: 12, color: "var(--text-3)" }}>
                      {__(
                        "Sync to download locale-specific reference data.",
                        "storeseeder",
                      )}
                    </div>
                  </>
                )}
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
              {syncStatus?.consent === "granted"
                ? __(
                    "Automatic download is allowed. Revoke to stop downloading on visit.",
                    "storeseeder",
                  )
                : syncStatus?.consent === "declined"
                  ? __(
                      "Automatic download is declined. Sync now reopens the consent prompt so you can allow it.",
                      "storeseeder",
                    )
                  : __(
                      "Nothing has been downloaded yet. Sync now asks for your permission first.",
                      "storeseeder",
                    )}
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
              <a
                href={SAMPLE_DATA_REPO_URL}
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
            <div className="fp-danger-act">
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
              <p className="fp-set-hint" style={{ marginTop: 7 }}>
                {__(
                  "Resets all settings to their default values.",
                  "storeseeder",
                )}
              </p>
            </div>
          </div>
        </SetCard>
      </div>
    </div>
  );
}
