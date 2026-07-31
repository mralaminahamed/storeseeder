import React from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { __, sprintf } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";
import { BrandIcon } from "@/components/ui/BrandIcon";
import { generatorsByCategory } from "@/lib/generators";
import { usePlatform } from "@/providers/PlatformProvider";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SidebarProps {
  collapsed: boolean;
  setCollapsed: (updater: (v: boolean) => boolean) => void;
  counts: Record<string, number>; // route → generated count
  openCmd: () => void;
}

// ---------------------------------------------------------------------------
// NavItem helper
// ---------------------------------------------------------------------------

interface NavItemProps {
  to: string;
  label: string;
  ic: IconName;
  count?: number;
  active: boolean;
  collapsed: boolean;
  testId?: string;
  /**
   * Why this generator is unavailable on the current target, or undefined when it
   * is available. Still navigable: the generator page states the reason in full,
   * and a nav item that refuses to respond reads as broken.
   */
  unavailable?: string;
}

function NavItem({ to, label, ic, count, active, collapsed, testId, unavailable }: NavItemProps) {
  const navigate = useNavigate();

  return (
    <button
      className={`fp-nav-item${active ? " active" : ""}${unavailable ? " unavailable" : ""}`}
      onClick={() => void navigate(to)}
      title={unavailable ?? (collapsed ? label : undefined)}
      data-testid={testId}
      data-unavailable={unavailable ? "true" : undefined}
    >
      <Icon name={ic} size={17} className="fp-nav-ic" stroke={1.7} />
      <span className="fp-nav-text">{label}</span>
      {"number" === typeof count && count > 0 && (
        <span className="fp-nav-count tnum">{count}</span>
      )}
    </button>
  );
}

// ---------------------------------------------------------------------------
// Sidebar
// ---------------------------------------------------------------------------

export function Sidebar({ collapsed, setCollapsed, counts, openCmd }: SidebarProps) {
  const { pathname } = useLocation();
  const { state, target, capability } = usePlatform();

  // Names the store being seeded rather than a fixed platform. Falls back to a
  // generic word when nothing is resolved, which is the case on a site with several
  // platforms active and no choice made yet.
  const platformLabel =
    state.platforms.find((p) => p.id === target)?.label ??
    __("Multi-platform", "storeseeder");

  const unavailableReason = (resource: string): string | undefined => {
    const cap = capability(resource);
    return cap && !cap.supported ? cap.reason : undefined;
  };

  return (
    <nav className={`fp-nav${collapsed ? " collapsed" : ""}`} data-testid="sidebar">
      {/* Brand */}
      <div className="fp-brand">
        <BrandIcon className="fp-brand-mark" size={30} />
        {!collapsed && (
          <div className="fp-brand-text">
            <div className="fp-brand-name">StoreSeeder</div>
            <div className="fp-brand-sub">{platformLabel}</div>
          </div>
        )}
        <button
          className="fp-nav-collapse fp-focusable"
          onClick={() => setCollapsed((c) => !c)}
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          <Icon name={collapsed ? "chevright" : "arrowleft"} size={16} />
        </button>
      </div>

      {/* Command button */}
      <button
        className="fp-cmd-btn fp-focusable"
        onClick={openCmd}
        title={collapsed ? "Search (⌘K)" : undefined}
        data-testid="cmd-button"
      >
        <Icon name="search" size={15} />
        <span className="fp-nav-text">Jump to…</span>
        <span className="kbd">
          <span className="kbd-key">⌘</span>
          <span className="kbd-key">K</span>
        </span>
      </button>

      {/* Scrollable nav area */}
      <div className="fp-nav-scroll">
        {/* Overview */}
        <NavItem
          to="/"
          label="Overview"
          ic="dashboard"
          active={pathname === "/"}
          collapsed={collapsed}
          testId="nav-overview"
        />

        {/* Groups. Order and grouping come from lib/generators, which the dashboard grid
            and the command palette also read — they used to answer this three ways. */}
        {generatorsByCategory().map((group) => {
          return (
            <div key={group.category}>
              <div className="fp-nav-group-label">
                {collapsed ? (
                  <span className="fp-nav-group-rule" />
                ) : (
                  sprintf(
                    /* translators: %s: category name, e.g. Core. */
                    __("%s generators", "storeseeder"),
                    group.label,
                  )
                )}
              </div>
              {group.items.map((g) => (
                <NavItem
                  key={g.route}
                  to={`/generator/${g.route}`}
                  label={g.name}
                  ic={g.iconName}
                  count={counts[g.route]}
                  active={pathname === `/generator/${g.route}`}
                  collapsed={collapsed}
                  testId={`nav-${g.route}`}
                  unavailable={unavailableReason(g.resource)}
                />
              ))}
            </div>
          );
        })}
      </div>

      {/* Footer */}
      <div className="fp-nav-foot">
        <NavItem
          to="/settings"
          label="Settings"
          ic="settings"
          active={pathname === "/settings"}
          collapsed={collapsed}
        />
        <NavItem
          to="/plugins"
          label="Our Plugins"
          ic="plug"
          active={pathname === "/plugins"}
          collapsed={collapsed}
        />
      </div>
    </nav>
  );
}

export default Sidebar;
