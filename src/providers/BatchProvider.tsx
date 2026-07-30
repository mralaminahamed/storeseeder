import React from "react";
import { createContext, useContext, useState, useCallback } from "@wordpress/element";
import { __, _n, sprintf } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";
import { usePlatform } from "@/providers/PlatformProvider";
import { AUTO } from "@/lib/platform";
import { getSettings } from "@/lib/settings";
import type { GeneratorResult } from "@/types";


export interface BatchItem {
  route: string;
  count: number;
}

interface BatchState {
  batch: BatchItem[];
  add: (route: string, count: number) => void;
  remove: (index: number) => void;
  setCount: (index: number, count: number) => void;
  clear: () => void;
  runAll: (onProgress?: (done: number, total: number) => void) => Promise<void>;
}

const BatchContext = createContext<BatchState | null>(null);

export function useBatch(): BatchState {
  const ctx = useContext(BatchContext);
  if (!ctx) throw new Error("useBatch must be used within BatchProvider");
  return ctx;
}

export function BatchProvider({ children }: { children: React.ReactNode }) {
  const [batch, setBatch] = useState<BatchItem[]>([]);
  const { recordRun } = useStats();
  const { target } = usePlatform();
  const { toast } = useToast();

  const add = useCallback((route: string, count: number) => {
    setBatch((bs) => {
      // Merge into an existing queued entry for the same generator.
      const idx = bs.findIndex((b) => b.route === route);
      if (idx >= 0) {
        return bs.map((b, i) =>
          i === idx ? { ...b, count: b.count + count } : b,
        );
      }
      return [...bs, { route, count }];
    });
  }, []);

  const remove = useCallback((index: number) => {
    setBatch((bs) => bs.filter((_, i) => i !== index));
  }, []);

  const setCount = useCallback((index: number, count: number) => {
    setBatch((bs) => bs.map((b, i) => (i === index ? { ...b, count } : b)));
  }, []);

  const clear = useCallback(() => setBatch([]), []);

  const runAll = useCallback(
    async (onProgress?: (done: number, total: number) => void) => {
      const items = batch;
      if (items.length === 0) return;

      const locale =
        getSettings().defaultLocale ??
        window.storeseederApi?.locale?.faker ??
        "en_US";

      let ok = 0;
      let total = 0;

      for (let i = 0; i < items.length; i++) {
        const item = items[i];
        try {
          const data = await apiFetch<GeneratorResult>({
            path: `/storeseeder/v1/${item.route}/generate`,
            method: "POST",
            // Explicit, like the single-run path. A queued run must land in the store
            // the topbar names, not wherever the server would guess.
            data: { count: item.count, locale, include_meta: false, platform: target ?? AUTO },
          });
          recordRun(item.route, item.count, true, data.message ?? "", { locale });
          ok += 1;
          total += item.count;
        } catch (err) {
          const errMsg =
            err instanceof Error
              ? err.message
              : __("An error occurred.", "storeseeder");
          recordRun(item.route, item.count, false, errMsg, { locale });
        }
        onProgress?.(i + 1, items.length);
      }

      if (ok > 0) {
        toast(
          sprintf(
            /* translators: %1$s: total items, %2$s: number of generators */
            __("Batch complete · %1$s items", "storeseeder"),
            total.toLocaleString(),
          ),
          sprintf(
            /* translators: %s: number of generators run */
            _n(
              "%s generator run",
              "%s generators run",
              ok,
              "storeseeder",
            ),
            ok.toLocaleString(),
          ),
        );
      } else {
        toast(__("Batch failed", "storeseeder"));
      }

      setBatch([]);
    },
    // `target` genuinely belongs here: without it the callback keeps whichever
    // platform was selected when it was created, and a queue run after switching
    // targets would write to the previous store.
    [batch, recordRun, toast, target],
  );

  return (
    <BatchContext.Provider
      value={{ batch, add, remove, setCount, clear, runAll }}
    >
      {children}
    </BatchContext.Provider>
  );
}
