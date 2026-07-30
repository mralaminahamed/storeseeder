import React from "react";
import { useNavigate } from "react-router-dom";
import { __, sprintf } from "@wordpress/i18n";
import { Button } from "@/components/ui/button";
import { SectionLabel } from "@/components/ui/section-label";
import { StatCard } from "@/components/dashboard/StatCard";
import { RecentActivity } from "@/components/dashboard/RecentActivity";
import { GeneratorGrid } from "@/components/home/GeneratorGrid";
import { useStats } from "@/providers/StatsProvider";
import { usePlatform } from "@/providers/PlatformProvider";

export default function HomePage() {
  const navigate = useNavigate();
  const { counts, totalGenerated, recentRuns } = useStats();
  const { state, target } = usePlatform();

  const targetName = state.platforms.find((p) => p.id === target)?.label;

  return (
    <div className="fp-page wide fp-enter">
      {/* Page header */}
      <div className="fp-page-head">
        <div>
          <h1 className="fp-h1">{__("StoreSeeder", "storeseeder")}</h1>
          <p className="fp-sub">
            {targetName
              ? sprintf(
                  /* translators: %s: e-commerce platform name. */
                  __("Generate realistic test data for your %s store.", "storeseeder"),
                  targetName,
                )
              : __("Generate realistic test data for your store.", "storeseeder")}
          </p>
        </div>
        <Button
          variant="primary"
          icon="plus"
          onClick={() => void navigate("/generator/products")}
        >
          {__("New generation", "storeseeder")}
        </Button>
      </div>

      {/* Stat cards */}
      <div className="fp-stat-row">
        <StatCard
          iconName="box"
          label={__("Products", "storeseeder")}
          value={counts.products ?? 0}
          empty={!counts.products}
          delta={counts.products ?? 0}
          spark={[4, 6, 5, 8, 7, 9, 10]}
          accentVar="var(--accent)"
          testId="stat-products"
        />
        <StatCard
          iconName="users"
          label={__("Customers", "storeseeder")}
          value={counts.customers ?? 0}
          empty={!counts.customers}
          delta={counts.customers ?? 0}
          spark={[2, 3, 3, 5, 6, 6, 8]}
          accentVar="var(--violet)"
          testId="stat-customers"
        />
        <StatCard
          iconName="cart"
          label={__("Orders", "storeseeder")}
          value={counts.orders ?? 0}
          empty={!counts.orders}
          delta={counts.orders ?? 0}
          spark={[1, 2, 4, 3, 6, 7, 9]}
          accentVar="var(--sky)"
          testId="stat-orders"
        />
        <StatCard
          iconName="database"
          label={__("Total Generated", "storeseeder")}
          value={totalGenerated}
          empty={!totalGenerated}
          delta={totalGenerated}
          spark={[3, 5, 8, 7, 11, 14, 18]}
          accentVar="var(--green)"
          testId="stat-total"
        />
      </div>

      {/* Recent activity */}
      <div className="fp-group-head">
        <SectionLabel>{__("Recent activity", "storeseeder")}</SectionLabel>
        <div className="fp-group-line" />
      </div>
      <RecentActivity runs={recentRuns} />

      {/* Generator grid */}
      <GeneratorGrid counts={counts} />
    </div>
  );
}
