import React from "react";

interface ToggleProps {
  checked: boolean;
  onChange: (v: boolean) => void;
  label?: string;
  hint?: string;
  /** Renders the row inert — for a setting the current user may read but not change. */
  disabled?: boolean;
  testId?: string;
}

/**
 * A switch and its caption, as one control.
 *
 * The row used to be a `<label>` wrapping a `role="switch"` button, which is not
 * an association a label can make — a button is not labelable the way an input is.
 * Making the whole row the button fixes that and keeps what the label gave for
 * free: clicking the caption still toggles, and the accessible name comes from the
 * row's own text rather than a separate aria binding.
 */
export function Toggle({
  checked,
  onChange,
  label,
  hint,
  disabled,
  testId,
}: ToggleProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={!!checked}
      disabled={disabled}
      data-testid={testId}
      onClick={() => onChange(!checked)}
      className="fp-toggle-row fp-focusable"
    >
      <span className={`fp-switch${checked ? " on" : ""}`}>
        <span className="fp-knob" />
      </span>
      {label && (
        <span className="fp-toggle-label">
          {label}
          {hint && <span className="fp-toggle-hint">{hint}</span>}
        </span>
      )}
    </button>
  );
}
