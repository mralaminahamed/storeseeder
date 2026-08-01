import React from "react";
import { useState, useEffect, useRef } from "@wordpress/element";
import { useLocation, Outlet } from "react-router-dom";

import { Sidebar } from "@/components/shell/Sidebar";
import { Topbar } from "@/components/shell/Topbar";
import { Toasts } from "@/components/overlays/Toasts";
import { ConsentModal } from "@/components/overlays/ConsentModal";
import { CommandPalette } from "@/components/overlays/CommandPalette";
import { LocalePicker } from "@/components/overlays/LocalePicker";
import { TweaksPanel } from "@/components/overlays/TweaksPanel";
import { BatchTray } from "@/components/overlays/BatchTray";
import { generators } from "@/lib/generators";
import { OPEN_TWEAKS_EVENT } from "@/lib/events";
import { defaultLocale, localeLabel } from "@/lib/locales";
import { getSettings, saveSettings } from "@/lib/settings";
import { useStats } from "@/providers/StatsProvider";
import { useBatch } from "@/providers/BatchProvider";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getNavCollapsedInit(): boolean {
  try {
    return localStorage.getItem("fp_nav_collapsed") === "1";
  } catch {
    return false;
  }
}

function deriveCrumb(pathname: string): string {
  if (pathname === "/") return "Overview";
  if (pathname === "/recipes") return "Recipes";
  if (pathname === "/settings") return "Settings";
  if (pathname === "/plugins") return "Our Plugins";
  if (pathname.startsWith("/generator/")) {
    const route = pathname.replace("/generator/", "");
    const gen = generators.find((g) => g.route === route);
    return gen ? gen.name : "Generator";
  }
  return "Overview";
}

// ---------------------------------------------------------------------------
// AppShell
// ---------------------------------------------------------------------------

export function AppShell() {
  const { pathname } = useLocation();

  // ---- nav collapsed (persisted) ----
  const [navCollapsed, setNavCollapsedRaw] = useState<boolean>(getNavCollapsedInit);

  const setNavCollapsed = (updater: (v: boolean) => boolean) => {
    setNavCollapsedRaw((prev) => {
      const next = updater(prev);
      try {
        localStorage.setItem("fp_nav_collapsed", next ? "1" : "0");
      } catch {}
      return next;
    });
  };

  // ---- locale ----
  // Backed by the same `defaultLocale` setting the generators read, so the topbar
  // control is the real one. It used to write its own `fp_locale` key that nothing
  // else consulted: picking Japanese changed the pill and generated English.
  const [locale, setLocaleRaw] = useState<string>(
    () => getSettings().defaultLocale || defaultLocale(),
  );

  const setLocale = (code: string) => {
    setLocaleRaw(code);
    saveSettings({ ...getSettings(), defaultLocale: code });
  };

  // ---- overlay open flags ----
  // Actual overlay components are added in a LATER task (Phase 7).
  // For now these flags are toggled by buttons but nothing renders them yet — that is expected.
  const [cmdOpen, setCmdOpen] = useState(false);
  const [tweaksOpen, setTweaksOpen] = useState(false);
  const [localeOpen, setLocaleOpen] = useState(false);
  const [batchOpen, setBatchOpen] = useState(false);

  // ---- global keyboard handler ----
  // Registered in the capture phase so ⌘K/Ctrl+K is intercepted before it
  // reaches WordPress core's command palette (also bound to ⌘K since WP 6.3).
  // stopImmediatePropagation() prevents core's palette from opening too.
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        e.stopImmediatePropagation();
        setCmdOpen((o) => !o);
      }
      if (e.key === "Escape") {
        setCmdOpen(false);
        setTweaksOpen(false);
        setLocaleOpen(false);
        setBatchOpen(false);
      }
    };
    window.addEventListener("keydown", handler, { capture: true });
    return () => window.removeEventListener("keydown", handler, { capture: true });
  }, []);

  // Settings offers theme and density too, and links to the panel for the rest —
  // accent, custom colors. An event rather than a prop: the Settings page is two
  // router levels down and nothing between the two needs to know about tweaks.
  useEffect(() => {
    const open = () => setTweaksOpen(true);
    window.addEventListener(OPEN_TWEAKS_EVENT, open);
    return () => window.removeEventListener(OPEN_TWEAKS_EVENT, open);
  }, []);

  // ---- scroll-to-top of content on route change ----
  const scrollRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (scrollRef.current) scrollRef.current.scrollTop = 0;
  }, [pathname]);

  // ---- derived state ----
  const crumb = deriveCrumb(pathname);
  const isGenerator = pathname.startsWith("/generator/");

  const { counts } = useStats();
  const { batch } = useBatch();

  return (
    <div className="fp-app" data-testid="app-shell">
      <div className="fp-shell">
        <Sidebar
          collapsed={navCollapsed}
          setCollapsed={setNavCollapsed}
          counts={counts}
          openCmd={() => setCmdOpen(true)}
        />
        <div className="fp-main">
          <Topbar
            crumb={crumb}
            locale={localeLabel(locale)}
            onOpenLocale={() => setLocaleOpen(true)}
            onOpenTweaks={() => setTweaksOpen(true)}
            batchCount={batch.length}
            onOpenBatch={() => setBatchOpen(true)}
          />
          {isGenerator ? (
            <div className="fp-main" style={{ overflow: "hidden" }}>
              <Outlet />
            </div>
          ) : (
            <div className="fp-scroll" ref={scrollRef}>
              <Outlet />
            </div>
          )}
        </div>
      </div>
      {cmdOpen && <CommandPalette onClose={() => setCmdOpen(false)} />}
      {tweaksOpen && <TweaksPanel onClose={() => setTweaksOpen(false)} />}
      {batchOpen && <BatchTray onClose={() => setBatchOpen(false)} />}
      {localeOpen && (
        <LocalePicker
          onClose={() => setLocaleOpen(false)}
          locale={locale}
          setLocale={setLocale}
        />
      )}
      <ConsentModal />
      <Toasts />
    </div>
  );
}

export default AppShell;
