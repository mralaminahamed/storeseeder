import React from "react";
import { __ } from "@wordpress/i18n";
import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";
import { Sparkline } from "./Sparkline";

interface StatCardProps {
  iconName: IconName;
  label: string;
  value: number;
  empty: boolean;
  delta: number;
  spark: number[];
  testId?: string;
}

/**
 * The tile and the sparkline carried a per-card hue — indigo, violet, sky, green. It read as a
 * category the data does not have: the four cards count the same kind of thing, and the generator
 * tiles a section below are all one accent, so the stat row looked like a different component.
 * Both now take the accent, which is what `.fp-stat-ic` declared all along before an inline style
 * overrode it.
 */
export function StatCard({ iconName, label, value, empty, delta, spark, testId }: StatCardProps) {
  return (
    <div className="fp-card fp-stat" data-testid={testId}>
      <div className="fp-stat-top">
        <span className="fp-stat-ic">
          <Icon name={iconName} size={19} />
        </span>
        {label}
      </div>

      <div
        className="fp-stat-num tnum"
        style={empty ? { color: "var(--text-faint)" } : undefined}
      >
        {empty ? "—" : value.toLocaleString()}
      </div>

      <div className="fp-stat-foot">
        {empty ? (
          <span className="fp-stat-empty">
            {__("Nothing generated yet", "storeseeder")}
          </span>
        ) : (
          <span className={`fp-stat-delta ${delta > 0 ? "up" : "flat"}`}>
            {delta > 0 && <Icon name="trend" size={13} />}
            {delta > 0 ? `+${delta} this week` : __("steady", "storeseeder")}
          </span>
        )}
        {spark && <Sparkline data={spark} />}
      </div>
    </div>
  );
}
