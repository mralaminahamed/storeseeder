import React from "react";
import type { FieldDescriptor } from "@/admin/lib/fieldsFromSchema";
import type { ParamValue } from "@/admin/types";
import { Toggle } from "@/admin/components/generator/fields/Toggle";
import { Chips } from "@/admin/components/generator/fields/Chips";
import { RangeField } from "@/admin/components/generator/fields/RangeField";
import { FieldSelect } from "@/admin/components/generator/fields/FieldSelect";
import { NumberField } from "@/admin/components/generator/fields/NumberField";
import { TextField } from "@/admin/components/generator/fields/TextField";

interface FieldProps {
  f: FieldDescriptor;
  value: ParamValue;
  onChange: (v: ParamValue) => void;
  hideLabel?: boolean;
}

/**
 * The stored value is whatever the parameter schema produced, so each control
 * checks it received its own shape and otherwise falls back to an empty one.
 * Previously every branch took `any` and a schema change would have reached the
 * control as the wrong type with no complaint.
 */
const asChips = (v: ParamValue): string[] => (Array.isArray(v) ? v : []);
const asText = (v: ParamValue): string => ("string" === typeof v ? v : "");
const asNumeric = (v: ParamValue): number | string =>
  "number" === typeof v || "string" === typeof v ? v : "";

function isRange(v: ParamValue): v is { lo: number; hi: number } {
  return "object" === typeof v && null !== v && !Array.isArray(v);
}

/**
 * Renders a single field control based on the FieldDescriptor type.
 *
 * A switch rather than a ternary chain: it dispatches on one discriminant, and the
 * chain it replaced nested five deep. Captions are a `<label>` only where the
 * control underneath is a real input — chips, ranges and the custom select are
 * built from buttons, so those groups carry `role="group"` and an `aria-label`
 * instead of a label pointing at nothing.
 */
export function Field({
  f,
  value,
  onChange,
  hideLabel,
}: FieldProps): JSX.Element {
  if ("toggle" === f.type) {
    // Toggle owns its own label rendering — ignore hideLabel.
    return (
      <div className="fp-field" data-param={f.key}>
        <Toggle checked={!!value} onChange={onChange} label={f.label} />
      </div>
    );
  }

  if ("number" === f.type || "text" === f.type) {
    const control =
      "number" === f.type ? (
        // NumberField accepts `number | string` as value and calls onChange with a
        // string. Callers that need a number must parse.
        <NumberField
          value={asNumeric(value) || (f.default as number | string) || ""}
          prefix={f.prefix}
          suffix={f.suffix}
          onChange={(s) => onChange(s)}
          width={160}
        />
      ) : (
        <TextField value={asText(value)} ph={f.ph} onChange={onChange} />
      );

    // Both wrap a native input, so containing it in the label associates the two
    // without needing an id to thread through.
    return (
      <div className="fp-field" data-param={f.key}>
        {hideLabel ? (
          control
        ) : (
          <label>
            <span className="fp-field-label">{f.label}</span>
            {control}
          </label>
        )}
      </div>
    );
  }

  let control: JSX.Element;
  switch (f.type) {
    case "chips":
      control = (
        <Chips
          options={f.options ?? []}
          value={asChips(value)}
          onChange={onChange}
        />
      );
      break;
    case "range":
      control = (
        <RangeField
          value={isRange(value) ? value : { lo: f.min ?? 0, hi: f.max ?? 100 }}
          min={f.min ?? 0}
          max={f.max ?? 100}
          prefix={f.prefix}
          suffix={f.suffix}
          onChange={onChange}
        />
      );
      break;
    default:
      control = (
        <FieldSelect
          value={asText(value) || (f.default as string) || ""}
          options={f.options ?? []}
          onChange={onChange}
          width={320}
        />
      );
  }

  return (
    <div
      className="fp-field"
      data-param={f.key}
      role="group"
      aria-label={f.label}
    >
      {!hideLabel && <span className="fp-field-label">{f.label}</span>}
      {control}
    </div>
  );
}
