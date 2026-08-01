import React, { useId } from "react";
import type { FieldDescriptor } from "@/lib/fieldsFromSchema";
import { optionLabel } from "@/lib/fieldsFromSchema";
import type { ParamValue } from "@/types";
import { Toggle } from "@/components/generator/fields/Toggle";
import { Chips } from "@/components/generator/fields/Chips";
import { RangeField } from "@/components/generator/fields/RangeField";
import { FieldSelect } from "@/components/generator/fields/FieldSelect";
import { NumberField } from "@/components/generator/fields/NumberField";
import { TextField } from "@/components/generator/fields/TextField";
import { EntityField } from "@/components/generator/fields/EntityField";

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
 * chain it replaced nested five deep. A caption is a `<label htmlFor>` wherever
 * there is a single control to bind to — the inputs, and the select's trigger
 * button. Chips and ranges have no such element, so those carry `role="group"`
 * and an `aria-label` rather than a label pointing at nothing.
 */
export function Field({
  f,
  value,
  onChange,
  hideLabel,
}: FieldProps): JSX.Element {
  const controlId = `ss-field-${useId().replace(/:/g, "")}`;
  if ("toggle" === f.type) {
    // Toggle owns its own label rendering — ignore hideLabel.
    return (
      <div className="fp-field" data-param={f.key}>
        <Toggle checked={!!value} onChange={onChange} label={f.label} />
      </div>
    );
  }

  // Grouped with the inputs below rather than the buttons: the picker's control is a native
  // <input>, so the caption binds to it by id like the other two.
  if ("entity" === f.type) {
    return (
      <div className="fp-field" data-param={f.key}>
        {!hideLabel && (
          <label className="fp-field-label" htmlFor={controlId}>
            {f.label}
          </label>
        )}
        <EntityField
          id={controlId}
          value={asText(value)}
          entity={f.entity ?? "product"}
          onChange={onChange}
        />
      </div>
    );
  }

  if ("number" === f.type || "text" === f.type) {
    const control =
      "number" === f.type ? (
        // NumberField accepts `number | string` as value and calls onChange with a
        // string. Callers that need a number must parse.
        <NumberField
          id={controlId}
          value={asNumeric(value) || (f.default as number | string) || ""}
          prefix={f.prefix}
          suffix={f.suffix}
          onChange={(s) => onChange(s)}
          width={160}
        />
      ) : (
        <TextField
          id={controlId}
          value={asText(value)}
          ph={f.ph}
          onChange={onChange}
        />
      );

    // Both render a native input, so the caption binds to it by id.
    return (
      <div className="fp-field" data-param={f.key}>
        {!hideLabel && (
          <label className="fp-field-label" htmlFor={controlId}>
            {f.label}
          </label>
        )}
        {control}
      </div>
    );
  }

  // Chips and ranges are built from buttons and a custom slider, so there is no
  // single control for a caption to point at; the select's trigger is a button,
  // which htmlFor may bind to.
  const isGroup = "chips" === f.type || "range" === f.type;

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
          id={controlId}
          value={asText(value) || (f.default as string) || ""}
          // Pairs, not bare strings: the value stays as the schema spells it and only the
          // label is made readable. FieldSelect keys its selection on the value.
          options={(f.options ?? []).map((option) => ({
            value: option,
            label: optionLabel(option),
          }))}
          onChange={onChange}
          width={320}
        />
      );
  }

  return (
    <div
      className="fp-field"
      data-param={f.key}
      role={isGroup ? "group" : undefined}
      aria-label={isGroup ? f.label : undefined}
    >
      {!hideLabel &&
        (isGroup ? (
          <span className="fp-field-label">{f.label}</span>
        ) : (
          <label className="fp-field-label" htmlFor={controlId}>
            {f.label}
          </label>
        ))}
      {control}
    </div>
  );
}
