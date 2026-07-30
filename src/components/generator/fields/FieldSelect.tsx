import React, { useState, useRef, useEffect } from "@wordpress/element";
import { Icon } from "@/lib/icons";

interface FieldSelectProps {
  value: string;
  options: string[];
  onChange: (v: string) => void;
  width?: number;
  /** Lets a caller bind its own <label htmlFor> to the trigger button. */
  id?: string;
}

export function FieldSelect({
  value,
  options,
  onChange,
  width,
  id,
}: FieldSelectProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);

  return (
    <div
      className="fp-select"
      ref={ref}
      style={width ? { maxWidth: width } : undefined}
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
        className={`fp-select-btn fp-focusable${open ? " open" : ""}`}
        onClick={() => setOpen((o) => !o)}
      >
        <span>{value}</span>
        <Icon name="updown" size={15} className="fp-select-caret" />
      </button>
      {open && (
        <div className="fp-select-pop fp-pop" role="listbox">
          {options.map((o) => (
            <button
              key={o}
              type="button"
              role="option"
              aria-selected={o === value}
              className={`fp-select-opt${o === value ? " sel" : ""}`}
              onClick={() => {
                onChange(o);
                setOpen(false);
              }}
            >
              <span>{o}</span>
              {o === value && <Icon name="check" size={15} stroke={2.2} />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
