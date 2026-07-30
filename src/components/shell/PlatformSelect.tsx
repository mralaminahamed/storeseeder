import React from "react";
import { useId } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Icon } from "@/lib/icons";
import { AUTO } from "@/lib/platform";
import { usePlatform } from "@/providers/PlatformProvider";

/**
 * Picks the store a run writes to.
 *
 * Sits immediately left of the locale pill, because the two answer the same kind
 * of question — locale decides what the data looks like, this decides where it
 * lands — and the second is the more consequential of the two.
 *
 * Renders nothing when only one platform is installed. A select with a single
 * option is a decision the user does not have, and showing it would imply
 * otherwise.
 */
export function PlatformSelect() {
  const { active, selected, label, setTarget, ambiguous } = usePlatform();
  const id = useId();

  if (active.length < 2) return null;

  return (
    <span className="fp-platform-select" data-testid="platform-select">
      <label htmlFor={id} className="screen-reader-text">
        {__("Target platform", "storeseeder")}
      </label>
      <Icon name="boxes" size={14} aria-hidden="true" />
      <select
        id={id}
        className="fp-platform-select-control fp-focusable"
        value={selected}
        onChange={(e) => void setTarget(e.target.value)}
        // Flagged rather than merely unset: with several platforms active and no
        // choice made, this control is the thing standing between the user and a
        // run, so it should look like it wants attention.
        data-needs-choice={ambiguous ? "true" : undefined}
        title={__("Which store generated data is written to", "storeseeder")}
      >
        <option value={AUTO}>{label}</option>
        {active.map((platform) => (
          <option key={platform.id} value={platform.id}>
            {platform.label}
          </option>
        ))}
      </select>
    </span>
  );
}

export default PlatformSelect;
