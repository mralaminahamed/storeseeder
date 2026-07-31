import React from "react";
import { useEffect, useMemo, useRef, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import { filterLocales, localeOptions, localePickerLabel } from "@/lib/locales";

interface LocalePickerProps {
  onClose: () => void;
  /** The currently selected locale **code**, e.g. `ja_JP`. */
  locale: string;
  /** Receives a locale **code**, never a label. */
  setLocale: (code: string) => void;
}

/**
 * Picks the locale generated data is produced in.
 *
 * Two things here are deliberate. It works in codes, not labels — an earlier version
 * stored the label, so the chosen value could never be sent to the API as-is. And it
 * has a filter: the list is 75 locales now that the picker offers everything the API
 * accepts, and a plain list that long is a scroll-hunt.
 */
export function LocalePicker({ onClose, locale, setLocale }: LocalePickerProps) {
  const [query, setQuery] = useState("");
  const inputRef = useRef<HTMLInputElement>(null);

  const options = useMemo(() => localeOptions(), []);
  const shown = useMemo(() => filterLocales(options, query), [options, query]);

  // Focus the filter on open, so typing narrows the list without a click first.
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ("Escape" === e.key) onClose();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div
      className="fp-overlay"
      style={{
        alignItems: "flex-start",
        justifyContent: "center",
        paddingTop: "14vh",
      }}
      role="presentation"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      data-testid="locale-picker"
    >
      <div
        className="fp-cmd-box"
        style={{ width: "min(460px,92vw)" }}
        role="dialog"
        aria-modal="true"
        aria-label={localePickerLabel()}
      >
        <div className="fp-cmd-input-row">
          <Icon name="globe" size={18} />
          <input
            ref={inputRef}
            type="search"
            className="fp-cmd-input"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={__("Search locales…", "storeseeder")}
            aria-label={__("Search locales", "storeseeder")}
            data-testid="locale-search"
          />
        </div>

        <div className="fp-cmd-results">
          {0 === shown.length && (
            <p className="fp-cmd-empty" data-testid="locale-empty">
              {sprintf(
                /* translators: %s: the search term the user typed. */
                __("No locale matches “%s”.", "storeseeder"),
                query,
              )}
            </p>
          )}

          {shown.map((o) => (
            <button
              key={o.code}
              type="button"
              className={`fp-cmd-item${o.code === locale ? " sel" : ""}`}
              onClick={() => {
                setLocale(o.code);
                onClose();
              }}
              data-testid={`locale-option-${o.code}`}
            >
              <Icon name="globe" size={16} className="fp-cmd-ic" />
              <span>{o.label}</span>
              <span className="fp-cmd-meta">
                {/* The check reads before the code, left to right: the tick marks the
                    row, the code identifies it. Reversed, the tick trailed the line and
                    scanned as part of the code. */}
                <span className="fp-cmd-check">
                  {o.code === locale && <Icon name="check" size={16} />}
                </span>
                {/* The code is shown as well as the label: it is what goes over the
                    API, and it is what a developer recognises. */}
                <code className="fp-cmd-hint">{o.code}</code>
              </span>
            </button>
          ))}
        </div>

        <div className="fp-cmd-foot">
          {sprintf(
            /* translators: %d: number of locales available. */
            __("%d locales available", "storeseeder"),
            options.length,
          )}
        </div>
      </div>
    </div>
  );
}
