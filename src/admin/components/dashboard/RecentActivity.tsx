import React from "react";
import { useNavigate } from "react-router-dom";
import { __ } from "@wordpress/i18n";
import { Icon } from "@/admin/lib/icons";
import { StatusPill } from "@/admin/components/ui/status-pill";
import { generators } from "@/admin/lib/generators";
import type { GlobalRun } from "@/admin/types";

function timeAgo(ts: number): string {
  const d = (Date.now() - ts) / 1000;
  if (d < 60) return __("just now", "fluent-cart-fakerpress");
  if (d < 3600) return Math.floor(d / 60) + "m ago";
  if (d < 86400) return Math.floor(d / 3600) + "h ago";
  return Math.floor(d / 86400) + "d ago";
}

interface RecentActivityProps {
  runs: GlobalRun[];
}

export function RecentActivity({ runs }: RecentActivityProps) {
  const navigate = useNavigate();

  if (runs.length === 0) {
    return (
      <div className="fp-card fp-recent">
        <div className="fp-empty">
          <Icon name="history" size={22} />
          <div>{__("No runs yet — generate something to see it here.", "fluent-cart-fakerpress")}</div>
        </div>
      </div>
    );
  }

  return (
    <div className="fp-card fp-recent">
      {runs.slice(0, 5).map((run, i) => {
        const gen = generators.find((g) => g.route === run.route);
        const iconName = gen?.iconName ?? "box";
        const name = gen?.name ?? run.route;

        const localePart = run.locale ? run.locale.split(" (")[0] : "";
        const seedPart = run.seed
          ? `· ${__("seed", "fluent-cart-fakerpress")} ${run.seed}`
          : `· ${__("random seed", "fluent-cart-fakerpress")}`;
        const meta = [localePart, seedPart].filter(Boolean).join(" ");

        return (
          <div
            key={i}
            className="fp-recent-row"
            onClick={() => navigate("/generator/" + run.route)}
          >
            <span className="fp-recent-ic">
              <Icon name={iconName} size={16} />
            </span>
            <div className="fp-recent-main">
              <div className="fp-recent-title">
                {__("Generated", "fluent-cart-fakerpress")} {run.count} {name.toLowerCase()}
              </div>
              <div className="fp-recent-meta">{meta}</div>
            </div>
            <StatusPill>{run.success ? __("Success", "fluent-cart-fakerpress") : __("Error", "fluent-cart-fakerpress")}</StatusPill>
            <span className="fp-recent-time">{timeAgo(run.timestamp)}</span>
          </div>
        );
      })}
    </div>
  );
}
