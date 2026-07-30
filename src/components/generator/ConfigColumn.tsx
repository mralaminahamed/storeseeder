import React from "react";
import { __ } from "@wordpress/i18n";
import { Icon } from "@/lib/icons";
import { SectionLabel } from "@/components/ui/section-label";
import { fieldsFromSchema, asParamValue } from "@/lib/fieldsFromSchema";
import { getPath } from "@/lib/paths";
import type { ParamBag } from "@/lib/paths";
import { Field } from "@/components/generator/FieldSection";
import type { Capability, Generator, ParamValue, PlatformInfo } from "@/types";

// ---------------------------------------------------------------------------
// Dependency notes keyed by generator route
// ---------------------------------------------------------------------------

const DEP: Record<string, () => string> = {
  refunds: () =>
    __("Targets existing completed / processing orders", "storeseeder"),
  "product-variations": () =>
    __("Applied to existing variable products", "storeseeder"),
  transactions: () => __("Generated against existing orders", "storeseeder"),
  "cart-sessions": () =>
    __("Uses your existing products & customers", "storeseeder"),
};

// ---------------------------------------------------------------------------
// ConfigColumn
// ---------------------------------------------------------------------------

interface ConfigColumnProps {
  generator: Generator;
  params: ParamBag;
  setField: (key: string, value: ParamValue) => void;
  /** True when several platforms are active and none has been chosen yet. */
  needsTarget?: boolean;
  /** The platforms that could be chosen. */
  platforms?: PlatformInfo[];
  onPickTarget?: (id: string) => void;
  /** Set when the chosen platform cannot represent this resource. */
  unsupported?: Capability | null;
}

/**
 * Left column of the Generator page — icon/name, description, optional
 * dependency note, and the full field sections derived from parameterConfig.
 */
export function ConfigColumn({
  generator,
  params,
  setField,
  needsTarget = false,
  platforms = [],
  onPickTarget,
  unsupported = null,
}: ConfigColumnProps): JSX.Element {
  const depNote = DEP[generator.route]?.();
  const sections = fieldsFromSchema(generator.parameterConfig ?? {});
  const hasFields =
    sections.length > 0 && sections.some((s) => s.fields.length > 0);

  return (
    <div className="fp-config-col">
      {/* Header: icon + name + optional "Popular" tag */}
      <div className="fp-config-head">
        <div className="fp-config-ic">
          <Icon name={generator.iconName} size={22} />
        </div>
        <div>
          <div className="fp-config-title">{generator.name}</div>
          {generator.popular && (
            <span
              className="fp-tag"
              style={{ marginTop: 6, display: "inline-block" }}
            >
              {__("Popular", "storeseeder")}
            </span>
          )}
        </div>
      </div>

      {/* Description */}
      <p className="fp-config-desc">{generator.description}</p>

      {/* Target platform prompt — the run is blocked until this is answered, so it
          sits above the fields rather than beside the Generate button. */}
      {needsTarget && (
        <div className="fp-dep fp-dep-warn" data-testid="target-prompt">
          <Icon name="boxes" size={15} />
          <div>
            <div style={{ fontWeight: 500 }}>
              {__("Choose where to write", "storeseeder")}
            </div>
            <p style={{ margin: "4px 0 8px", color: "var(--text-faint)", fontSize: 13 }}>
              {__(
                "More than one e-commerce platform is active, so there is no safe default.",
                "storeseeder",
              )}
            </p>
            <div className="fp-target-choices">
              {platforms.map((platform) => (
                <button
                  key={platform.id}
                  type="button"
                  className="fp-btn fp-btn-outline fp-btn-sm"
                  onClick={() => onPickTarget?.(platform.id)}
                  data-testid={`target-choice-${platform.id}`}
                >
                  {platform.label}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Unsupported on the chosen platform. Says which plugin would enable it when
          one would, because a dimmed control that explains nothing is a dead end. */}
      {unsupported && (
        <div className="fp-dep fp-dep-warn" data-testid="unsupported-notice">
          <Icon name="info" size={15} />
          <div>
            <div style={{ fontWeight: 500 }}>
              {__("Not available here", "storeseeder")}
            </div>
            <p style={{ margin: "4px 0 0", color: "var(--text-faint)", fontSize: 13 }}>
              {unsupported.reason}
            </p>
          </div>
        </div>
      )}

      {/* Dependency note */}
      {depNote && (
        <div className="fp-dep">
          <Icon name="info" size={15} />
          {depNote}
        </div>
      )}

      {/* Field sections */}
      {!hasFields ? (
        <div className="fp-field-section">
          <p style={{ color: "var(--text-faint)", fontSize: 13 }}>
            {__(
              "No extra options — just set a count and generate.",
              "storeseeder",
            )}
          </p>
        </div>
      ) : (
        sections.map((section, si) => {
          const allNum =
            section.fields.length === 2 &&
            section.fields.every((f) => f.type === "number");

          const dup =
            section.fields.length === 1 &&
            section.fields[0].label.toLowerCase() ===
              section.name.toLowerCase() &&
            section.fields[0].type !== "toggle";

          return (
            <div key={si} className="fp-field-section">
              <SectionLabel>{section.name}</SectionLabel>

              {allNum ? (
                <div className="fp-field-2col">
                  {section.fields.map((f) => (
                    <Field
                      key={f.key}
                      f={f}
                      value={asParamValue(getPath(params, f.key))}
                      onChange={(v) => setField(f.key, v)}
                    />
                  ))}
                </div>
              ) : (
                section.fields.map((f) => (
                  <Field
                    key={f.key}
                    f={f}
                    value={asParamValue(getPath(params, f.key))}
                    onChange={(v) => setField(f.key, v)}
                    hideLabel={dup}
                  />
                ))
              )}
            </div>
          );
        })
      )}
    </div>
  );
}
