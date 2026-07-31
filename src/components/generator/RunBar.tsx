import React from "react";
import { __, sprintf } from "@wordpress/i18n";
import { Button } from "@/components/ui/button";
import { Stepper } from "@/components/generator/fields/Stepper";
import { TextField } from "@/components/generator/fields/TextField";
import { Toggle } from "@/components/generator/fields/Toggle";

// ---------------------------------------------------------------------------
// RunBar — sticky bottom bar with count / seed / meta controls + action btns
// ---------------------------------------------------------------------------

interface RunBarProps {
  count: number;
  seed: string;
  meta: boolean;
  onCount: (v: number) => void;
  onSeed: (v: string) => void;
  onMeta: (v: boolean) => void;
  onGenerate: () => void;
  onAddBatch: () => void;
  generating: boolean;
  /**
   * Blocks both actions when the run cannot proceed — no target chosen, or a target
   * that cannot represent this resource. Queueing it to the batch is blocked too,
   * since the batch would only fail later and further from the explanation.
   */
  disabled?: boolean;
}

export function RunBar({
  count,
  seed,
  meta,
  onCount,
  onSeed,
  onMeta,
  onGenerate,
  onAddBatch,
  generating,
  disabled = false,
}: RunBarProps): JSX.Element {
  const generateLabel =
    count === 1
      ? sprintf(
          /* translators: %s: formatted number */
          __("Generate %s item", "storeseeder"),
          count.toLocaleString(),
        )
      : sprintf(
          /* translators: %s: formatted number */
          __("Generate %s items", "storeseeder"),
          count.toLocaleString(),
        );

  return (
    <div className="fp-genbar" data-testid="generator-runbar">
      {/* Count group */}
      <div className="fp-genbar-ctl">
        <span className="lbl">{__("Count", "storeseeder")}</span>
        <Stepper value={count} onChange={onCount} min={1} max={100000} testId="count" />
      </div>

      <div className="fp-genbar-sep" />

      {/* Seed group */}
      <div className="fp-genbar-ctl">
        <span className="lbl">{__("Seed", "storeseeder")}</span>
        <div className="fp-seed-input">
          <TextField value={seed} ph="random" onChange={onSeed} />
        </div>
      </div>

      <div className="fp-genbar-sep" />

      {/* Metadata toggle */}
      <Toggle
        checked={meta}
        onChange={onMeta}
        label={__("Metadata", "storeseeder")}
      />

      <div className="fp-genbar-spacer" />

      {/* Add to batch */}
      <Button
        variant="outline"
        size="lg"
        icon="layers"
        onClick={onAddBatch}
        disabled={disabled}
        type="button"
        data-testid="add-to-batch"
      >
        {__("Add to batch", "storeseeder")}
      </Button>

      {/* Generate */}
      <Button
        variant="primary"
        size="lg"
        icon="play"
        onClick={onGenerate}
        disabled={generating || disabled}
        type="button"
        data-testid="generate-btn"
      >
        {generateLabel}
      </Button>
    </div>
  );
}
