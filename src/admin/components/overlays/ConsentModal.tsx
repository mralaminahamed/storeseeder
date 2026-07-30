import React from "react";
import { useState, useEffect, useRef, useCallback } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import { Button } from "@/admin/components/ui/button";
import { Icon } from "@/admin/lib/icons";
import {
  SHOW_CONSENT_EVENT,
  notifyConsentChanged,
  readJsonBody,
  bodyMessage,
  parseSampleDataStatus,
} from "@/admin/lib/consent";
import { useToast } from "@/admin/providers/ToastProvider";

const REPO_URL =
  "https://github.com/mralaminahamed/storeseeder-sample-data-fluent-cart";

/**
 * Consent gate for the optional GitHub sample-data download.
 *
 * Mounted once at the app shell. Because the React app mounts only on the
 * plugin admin page, this overlay can never appear on any other admin screen.
 * Self-gating on load: shows only when the site-wide decision is undecided AND
 * no sample data is present yet, so a site that has already decided is never
 * nagged.
 *
 * Settings can reopen it on demand via the SHOW_CONSENT_EVENT trigger, which
 * bypasses that gate — otherwise the prompt would be unreachable once any
 * decision had been recorded.
 */
export function ConsentModal() {
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const ranRef = useRef(false);

  // Settings asks for the prompt explicitly; ignore the load-time gate.
  useEffect(() => {
    const reopen = () => {
      setBusy(false);
      setOpen(true);
    };

    window.addEventListener(SHOW_CONSENT_EVENT, reopen);
    return () => window.removeEventListener(SHOW_CONSENT_EVENT, reopen);
  }, []);

  const api = window.storeseederApi;
  const restUrl = api?.restUrl ?? "";
  const nonce = api?.restNonce ?? "";

  useEffect(() => {
    if (ranRef.current || !restUrl) return;
    ranRef.current = true;
    let cancelled = false;

    fetch(`${restUrl}download-sample`, { headers: { "X-WP-Nonce": nonce } })
      .then(async (r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return parseSampleDataStatus(await readJsonBody(r));
      })
      .then((s) => {
        if (cancelled || !s) return;
        if (null === s.consent) {
          // Undecided: only prompt if the data isn't already present — a
          // pre-existing install should not be nagged.
          if (!s.exists) {
            setOpen(true);
          }
        } else if (s.consent === "granted" && !s.exists) {
          // Consent already given but files are missing — re-fetch silently.
          void fetch(`${restUrl}download-sample`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": nonce,
            },
            body: JSON.stringify({ force: false }),
          }).catch(() => {});
        }
      })
      .catch(() => {
        /* Fail closed: on a status error, show no modal and download nothing. */
      });

    return () => {
      cancelled = true;
    };
  }, [restUrl, nonce]);

  const allow = useCallback(async () => {
    setBusy(true);
    try {
      const res = await fetch(`${restUrl}download-sample`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
        body: JSON.stringify({ force: false }),
      });
      const body = await readJsonBody(res);
      if (!res.ok) {
        throw new Error(bodyMessage(body, `HTTP ${res.status}`));
      }
      toast(__("Sample data downloaded", "storeseeder"));
      notifyConsentChanged();
      setOpen(false);
    } catch {
      toast(
        __("Download failed — you can retry from Settings.", "storeseeder"),
      );
      setBusy(false);
    }
  }, [restUrl, nonce, toast]);

  const decline = useCallback(async () => {
    setBusy(true);
    try {
      const res = await fetch(`${restUrl}download-sample/consent`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
        body: JSON.stringify({ granted: false }),
      });
      // Closing has to mean the decision was stored, otherwise the prompt
      // silently returns on the next visit as though nothing was chosen.
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      notifyConsentChanged();
      setOpen(false);
    } catch {
      toast(
        __("Could not save your choice — please try again.", "storeseeder"),
      );
      setBusy(false);
    }
  }, [restUrl, nonce, toast]);

  if (!open) return null;

  return (
    <div
      className="fp-overlay"
      style={{ alignItems: "center", justifyContent: "center" }}
    >
      <div
        className="fp-consent"
        role="dialog"
        aria-modal="true"
        aria-labelledby="fp-consent-title"
        data-testid="consent-modal"
      >
        <span className="fp-consent-ic">
          <Icon name="database" size={22} />
        </span>
        <h2 id="fp-consent-title" className="fp-consent-title">
          {__("Download sample data?", "storeseeder")}
        </h2>
        <p className="fp-consent-text">
          {__(
            "StoreSeeder can download locale-specific reference data (product names, addresses and customer tags for 75+ locales) from GitHub to make generated content more realistic. No data about your site is sent.",
            "storeseeder",
          )}
        </p>
        <p className="fp-consent-text">
          {__(
            "You can decline and still generate data using built-in defaults, or change this later in Settings.",
            "storeseeder",
          )}
        </p>
        <a
          className="fp-consent-link"
          href={REPO_URL}
          target="_blank"
          rel="noopener noreferrer"
        >
          <Icon name="external" size={14} />
          {__("View the sample data repository", "storeseeder")}
        </a>
        <div className="fp-consent-actions">
          <Button
            variant="outline"
            onClick={() => void decline()}
            disabled={busy}
            data-testid="consent-decline"
          >
            {__("Not now", "storeseeder")}
          </Button>
          <Button
            variant="primary"
            icon="download"
            onClick={() => void allow()}
            disabled={busy}
            data-testid="consent-allow"
          >
            {busy
              ? __("Downloading…", "storeseeder")
              : __("Allow & download", "storeseeder")}
          </Button>
        </div>
      </div>
    </div>
  );
}
