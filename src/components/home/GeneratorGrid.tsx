import React from "react";
import { useNavigate } from "react-router-dom";
import { __, sprintf } from "@wordpress/i18n";
import { Icon } from "@/lib/icons";
import { SectionLabel } from "@/components/ui/section-label";
import { generatorsByCategory } from "@/lib/generators";
import { usePlatform } from "@/providers/PlatformProvider";

interface GeneratorGridProps {
  counts: Record<string, number>;
}

export function GeneratorGrid({ counts }: GeneratorGridProps) {
  const navigate = useNavigate();
  const { capability } = usePlatform();

  return (
    <div data-testid="generator-grid">
      {generatorsByCategory().map(({ category, label, items: group }) => {
        return (
          <div key={category}>
            <div className="fp-group-head">
              <SectionLabel>
                {sprintf(
                  /* translators: %s: category name, e.g. Core. */
                  __("%s generators", "storeseeder"),
                  label,
                )}
              </SectionLabel>
              <div className="fp-group-line" />
            </div>
            <div className="fp-gen-grid">
              {group.map((g) => {
                const cap = capability(g.resource);
                const unavailable = cap && !cap.supported ? cap.reason : undefined;
                const runCount = counts[g.route];

                const runNote = runCount
                  ? `${runCount} ${__("generated", "storeseeder")}`
                  : __("Not run yet", "storeseeder");
                const footNote = unavailable
                  ? __("Unavailable here", "storeseeder")
                  : runNote;

                return (
                <button
                  key={g.route}
                  className={`fp-gen-card${unavailable ? " unavailable" : ""}`}
                  onClick={() => void navigate(`/generator/${g.route}`)}
                  data-testid={`gen-card-${g.route}`}
                  data-unavailable={unavailable ? "true" : undefined}
                  // Still navigable. The generator page explains the reason in full
                  // and offers the choice of another target; a card that swallows
                  // clicks reads as a bug.
                  title={unavailable}
                >
                  <div className="fp-gen-card-top">
                    <span className="fp-gen-ic">
                      <Icon name={g.iconName} size={19} />
                    </span>
                    {g.popular && (
                      <span className="fp-tag">
                        {__("Popular", "storeseeder")}
                      </span>
                    )}
                  </div>
                  <div className="fp-gen-name">{g.name}</div>
                  <div className="fp-gen-desc">{g.description}</div>
                  <div className="fp-gen-foot">
                    <span className="fp-gen-gen">
                      {footNote}
                    </span>
                    <Icon name="chevright" size={16} className="fp-gen-arrow" />
                  </div>
                </button>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
  );
}
