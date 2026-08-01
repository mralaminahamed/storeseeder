import React from "react";
import { Icon } from "@/lib/icons";
import { optionLabel } from "@/lib/fieldsFromSchema";

interface ChipsProps {
  /** Option **values**, as the schema and the REST API spell them. */
  options: string[];
  value: string[];
  onChange: (v: string[]) => void;
}

/**
 * A multi-select rendered as toggleable chips.
 *
 * The chip shows a readable label and carries the raw value on `data-chip-value`, so what a
 * reader sees and what the API receives can differ without the two drifting: `aria-pressed`
 * and the selection state are keyed on the value throughout.
 */
export function Chips({ options, value, onChange }: ChipsProps) {
  const toggle = (o: string) =>
    onChange(
      value.includes(o) ? value.filter((x) => x !== o) : [...value, o],
    );

  return (
    <div className="fp-chips">
      {options.map((o) => (
        <button
          key={o}
          type="button"
          onClick={() => toggle(o)}
          aria-pressed={value.includes(o)}
          className={`fp-chip fp-focusable${value.includes(o) ? " on" : ""}`}
          data-chip-value={o}
        >
          {value.includes(o) && <Icon name="check" size={13} stroke={2.4} />}
          <span>{optionLabel(o)}</span>
        </button>
      ))}
    </div>
  );
}
