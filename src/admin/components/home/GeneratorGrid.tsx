import React from "react";
import { useNavigate } from "react-router-dom";
import { __ } from "@wordpress/i18n";
import { Icon } from "@/admin/lib/icons";
import { SectionLabel } from "@/admin/components/ui/section-label";
import { generators } from "@/admin/lib/generators";

interface GeneratorGridProps {
  counts: Record<string, number>;
}

const CATEGORY_ORDER = [
  __("Core", "fluent-cart-fakerpress"),
  __("Advanced", "fluent-cart-fakerpress"),
  __("Enhanced", "fluent-cart-fakerpress"),
];

export function GeneratorGrid({ counts }: GeneratorGridProps) {
  const navigate = useNavigate();

  const categories = CATEGORY_ORDER.filter((cat) =>
    generators.some((g) => g.category === cat),
  );

  return (
    <div data-testid="generator-grid">
      {categories.map((category) => {
        const group = generators
          .filter((g) => g.category === category)
          .sort((a, b) => a.order - b.order);

        return (
          <div key={category}>
            <div className="fp-group-head">
              <SectionLabel>
                {category} {__("generators", "fluent-cart-fakerpress")}
              </SectionLabel>
              <div className="fp-group-line" />
            </div>
            <div className="fp-gen-grid">
              {group.map((g) => (
                <button
                  key={g.route}
                  className="fp-gen-card"
                  onClick={() => navigate(`/generator/${g.route}`)}
                  data-testid={`gen-card-${g.route}`}
                >
                  <div className="fp-gen-card-top">
                    <span className="fp-gen-ic">
                      <Icon name={g.iconName} size={19} />
                    </span>
                    {g.popular && (
                      <span className="fp-tag">
                        {__("Popular", "fluent-cart-fakerpress")}
                      </span>
                    )}
                  </div>
                  <div className="fp-gen-name">{g.name}</div>
                  <div className="fp-gen-desc">{g.description}</div>
                  <div className="fp-gen-foot">
                    <span className="fp-gen-gen">
                      {counts[g.route]
                        ? `${counts[g.route]} ${__("generated", "fluent-cart-fakerpress")}`
                        : __("Not run yet", "fluent-cart-fakerpress")}
                    </span>
                    <Icon name="chevright" size={16} className="fp-gen-arrow" />
                  </div>
                </button>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}
