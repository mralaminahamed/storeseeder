import React, { useState, useRef, useEffect } from "@wordpress/element";
import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";

/**
 * One option: a bare string when the value *is* the label, or a pair when they differ.
 *
 * The pair exists because every caller needing one was mapping a label back to a value
 * with `options.find( o => o.label === label )` — which is how the locale picker came to
 * store a display label and try to send it to the REST API.
 */
export type SelectOption = string | { value: string; label: string };

interface FieldSelectProps {
  /** The current **value**. For a bare-string option, the value is the label. */
  value: string;
  options: SelectOption[];
  onChange: (v: string) => void;
  width?: number;
  /** Lets a caller bind its own <label htmlFor> to the trigger button. */
  id?: string;
  /**
   * `field` fills its column, as on a settings or generator form. `pill` is the compact
   * topbar treatment, sized to its content so it sits beside the locale pill.
   */
  variant?: "field" | "pill";
  /** Shown inside the trigger, left of the value. */
  icon?: IconName;
  title?: string;
  /**
   * Marks the control as the thing standing between the user and their next action, so
   * it asks for attention rather than merely looking unset.
   */
  needsChoice?: boolean;
  testId?: string;
  ariaLabel?: string;
}

function normalize(option: SelectOption): { value: string; label: string } {
  return "string" === typeof option ? { value: option, label: option } : option;
}

export function FieldSelect({
  value,
  options,
  onChange,
  width,
  id,
  variant = "field",
  icon,
  title,
  needsChoice,
  testId,
  ariaLabel,
}: FieldSelectProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const items = options.map(normalize);
  const current = items.find((o) => o.value === value);

  useEffect(() => {
    const h = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);

  // Escape closes without choosing. A dropdown dismissable only by clicking elsewhere
  // leaves a keyboard user stuck in it.
  useEffect(() => {
    if (!open) return;

    const onKey = (e: KeyboardEvent) => {
      if ("Escape" === e.key) {
        e.stopPropagation();
        setOpen(false);
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <div
      className={`fp-select${"pill" === variant ? " is-pill" : ""}`}
      ref={ref}
      style={width ? { maxWidth: width } : undefined}
      data-testid={testId}
    >
      {/* This is a combobox controlling a listbox, and it now says so: assistive
          tech had no way to tell the trigger from an ordinary button, and no way
          to know whether the list was open or which option was current. */}
      <button
        id={id}
        type="button"
        role="combobox"
        aria-expanded={open}
        aria-haspopup="listbox"
        aria-label={ariaLabel}
        title={title}
        data-needs-choice={needsChoice ? "true" : undefined}
        className={`fp-select-btn fp-focusable${open ? " open" : ""}`}
        onClick={() => setOpen((o) => !o)}
      >
        {icon && <Icon name={icon} size={14} className="fp-select-ic" />}
        <span>{current?.label ?? value}</span>
        <Icon name="updown" size={15} className="fp-select-caret" />
      </button>
      {open && (
        <div className="fp-select-pop fp-pop" role="listbox">
          {items.map((o) => (
            <button
              key={o.value}
              type="button"
              role="option"
              aria-selected={o.value === value}
              className={`fp-select-opt${o.value === value ? " sel" : ""}`}
              onClick={() => {
                onChange(o.value);
                setOpen(false);
              }}
            >
              <span>{o.label}</span>
              {o.value === value && <Icon name="check" size={15} stroke={2.2} />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
