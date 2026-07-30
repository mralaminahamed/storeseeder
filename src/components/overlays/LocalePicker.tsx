import React from "react";
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";

interface LocalePickerProps {
  onClose: () => void;
  locale: string;
  setLocale: (l: string) => void;
}

export function LocalePicker({ onClose, locale, setLocale }: LocalePickerProps) {
  const all = window.storeseederApi?.locale?.allLocales ?? {};
  // Sorted list of human labels; fall back to the current locale if none provided.
  const labels = Object.values(all).filter(Boolean).sort();
  const options = labels.length > 0 ? labels : [locale];

  // Escape closes it. Previously the only way out was a click, which left keyboard
  // users stuck in the overlay.
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
        style={{ width: "min(420px,92vw)" }}
        role="dialog"
        aria-modal="true"
        aria-label={__("Default locale", "storeseeder")}
      >
        <div className="fp-cmd-input-row">
          <Icon name="globe" size={18} />
          <span style={{ fontWeight: 600 }}>
            {__("Default locale", "storeseeder")}
          </span>
        </div>
        <div className="fp-cmd-results">
          {options.map((l) => (
            <button
              key={l}
              type="button"
              className={`fp-cmd-item${l === locale ? " sel" : ""}`}
              onClick={() => {
                setLocale(l);
                onClose();
              }}
            >
              <Icon name="globe" size={16} className="fp-cmd-ic" />
              <span>{l}</span>
              {l === locale && (
                <Icon
                  name="check"
                  size={16}
                  style={{ marginLeft: "auto", color: "var(--accent)" }}
                />
              )}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
