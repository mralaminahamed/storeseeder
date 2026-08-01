import React from "react";
import { useCallback, useRef } from "@wordpress/element";

import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";

export interface TabDef<T extends string> {
  id: T;
  label: string;
  ic?: IconName;
  /** Shown after the label — a count, or a warning marker. */
  badge?: string;
}

/**
 * A tab strip for switching between panels of one page.
 *
 * Not `Seg`. That is a segmented *control*: a group of `aria-pressed` buttons for choosing a value, and
 * it is right for theme or density. Tabs navigate between regions, which assistive technology treats
 * differently — a tablist announces "tab 2 of 3" and answers arrow keys, where a group of toggle buttons
 * announces three separate pressed states and answers nothing. Using the segmented control here would
 * have looked identical and told a screen reader the wrong thing.
 *
 * Keyboard behaviour follows the ARIA practices for a manually-activated tablist: Left/Right move
 * between tabs, Home/End jump to the ends, and only the selected tab is in the tab order — so Tab from
 * the strip lands in the panel rather than walking through every other tab first.
 */
export function Tabs<T extends string>({
  tabs,
  active,
  onChange,
  ariaLabel,
  idPrefix,
}: {
  tabs: ReadonlyArray<TabDef<T>>;
  active: T;
  onChange: (id: T) => void;
  ariaLabel: string;
  /** Namespaces the generated ids, so two tablists on one page cannot collide. */
  idPrefix: string;
}) {
  const strip = useRef<HTMLDivElement | null>(null);

  const focusTab = useCallback((index: number) => {
    const buttons = strip.current?.querySelectorAll<HTMLButtonElement>('[role="tab"]');

    buttons?.[index]?.focus();
  }, []);

  /*
   * On the tabs, not on the strip. A keydown handler on the container makes it an interactive element
   * that is not focusable — which eslint's `jsx-a11y/interactive-supports-focus` correctly rejects, and
   * which would also be wrong: under a roving tab index the focus is always on a tab, so that is where
   * the key arrives.
   */
  const onKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>) => {
      const current = tabs.findIndex((tab) => tab.id === active);
      const last = tabs.length - 1;

      let next: number | null = null;

      if ("ArrowRight" === event.key) next = current >= last ? 0 : current + 1;
      if ("ArrowLeft" === event.key) next = current <= 0 ? last : current - 1;
      if ("Home" === event.key) next = 0;
      if ("End" === event.key) next = last;

      if (null === next) return;

      // Prevent the arrow keys from scrolling the panel underneath while the strip has focus.
      event.preventDefault();
      onChange(tabs[next].id);
      focusTab(next);
    },
    [tabs, active, onChange, focusTab],
  );

  return (
    <div className="fp-tabs" role="tablist" aria-label={ariaLabel} ref={strip}>
      {tabs.map((tab) => {
        const selected = tab.id === active;

        return (
          <button
            key={tab.id}
            type="button"
            role="tab"
            id={`${idPrefix}-tab-${tab.id}`}
            aria-selected={selected}
            aria-controls={`${idPrefix}-panel-${tab.id}`}
            // Roving tab index: the strip is one stop, not one per tab.
            tabIndex={selected ? 0 : -1}
            className={`fp-tab${selected ? " on" : ""} fp-focusable`}
            onClick={() => onChange(tab.id)}
            onKeyDown={onKeyDown}
            data-testid={`${idPrefix}-tab-${tab.id}`}
          >
            {tab.ic && <Icon name={tab.ic} size={15} className="fp-tab-ic" />}
            <span>{tab.label}</span>
            {tab.badge && <span className="fp-tab-badge">{tab.badge}</span>}
          </button>
        );
      })}
    </div>
  );
}

/**
 * The panel a tab controls.
 *
 * Rendered only when selected — the alternative is `hidden` on the inactive ones, which keeps their
 * state but also keeps their network requests and their skeletons alive off-screen.
 *
 * `tabIndex={0}` because the panel is the keyboard destination after the strip, and a panel whose first
 * child is not focusable would otherwise be skipped entirely.
 */
export function TabPanel({
  id,
  idPrefix,
  children,
}: {
  id: string;
  idPrefix: string;
  children: React.ReactNode;
}) {
  return (
    <div
      role="tabpanel"
      id={`${idPrefix}-panel-${id}`}
      aria-labelledby={`${idPrefix}-tab-${id}`}
      tabIndex={0}
      className="fp-tabpanel"
    >
      {children}
    </div>
  );
}
