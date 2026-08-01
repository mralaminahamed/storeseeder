import React from "react";
import { __, sprintf } from "@wordpress/i18n";

import { FieldSelect } from "@/components/generator/fields/FieldSelect";
import { AUTO } from "@/lib/platform";
import { usePlatform } from "@/providers/PlatformProvider";

/**
 * Picks the store a run writes to.
 *
 * Sits immediately left of the locale pill, because the two answer the same kind of
 * question — locale decides what the data looks like, this decides where it lands — and
 * the second is the more consequential of the two.
 *
 * Renders nothing when fewer than two platforms are installed. A select with one option
 * is a decision the user does not have, and showing it would imply otherwise; the
 * Settings page still names the target either way.
 *
 * Uses the app's own select rather than a native `<select>`: the native one cannot carry
 * the icon, the pill treatment or the check mark the rest of the admin uses, and it
 * draws its list in the operating system's chrome — a light menu over a dark app.
 */
export function PlatformSelect() {
  const { active, selected, label, setTarget, ambiguous, state } = usePlatform();

  if (active.length < 2) return null;

  // Only meaningful while Auto is selected; an explicit choice is already named on the pill.
  const resolvedLabel = active.find((platform) => platform.id === state.resolved)?.label ?? "";

  return (
    <FieldSelect
      variant="pill"
      icon="store"
      testId="platform-select"
      ariaLabel={__("Target platform", "storeseeder")}
      title={
        selected === AUTO && resolvedLabel
          ? sprintf(
              /* translators: %s: name of the platform Auto currently resolves to. */
              __("Which store generated data is written to — currently %s", "storeseeder"),
              resolvedLabel,
            )
          : __("Which store generated data is written to", "storeseeder")
      }
      value={selected}
      // Auto is always first and always reads "Auto" — it is a mode, and a mode renamed
      // after whichever store it currently resolves to reads as a different option every
      // time the list is opened. What it resolved to is on the tooltip.
      options={[
        { value: AUTO, label },
        ...active.map((platform) => ({
          value: platform.id,
          label: platform.label,
        })),
      ]}
      onChange={(platform) => void setTarget(platform)}
      // Flagged rather than merely unset: with several platforms active and no choice
      // made, this control is the thing standing between the user and a run.
      needsChoice={ambiguous}
    />
  );
}

export default PlatformSelect;
