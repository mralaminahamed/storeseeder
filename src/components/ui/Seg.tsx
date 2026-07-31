import React from "react";

import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";

export interface SegOption<T> {
  v: T;
  label: string;
  ic?: IconName;
}

/**
 * Segmented control: two or three mutually exclusive choices, all visible.
 *
 * Lives here rather than in TweaksPanel because the Settings page needs the same
 * control for the same settings — theme and density are reachable from both, and two
 * copies of a control that has to look identical is one copy too many.
 */
export function Seg<T extends string>({
  value,
  options,
  onChange,
  ariaLabel,
}: {
  value: T;
  options: SegOption<T>[];
  onChange: (v: T) => void;
  ariaLabel?: string;
}) {
  return (
    <div className="fp-seg" role="group" aria-label={ariaLabel}>
      {options.map((o) => (
        <button
          key={o.v}
          type="button"
          className={`fp-seg-btn${value === o.v ? " on" : ""}`}
          aria-pressed={value === o.v}
          onClick={() => onChange(o.v)}
        >
          {o.ic && <Icon name={o.ic} size={15} />}
          {o.label}
        </button>
      ))}
    </div>
  );
}
