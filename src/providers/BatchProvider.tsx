import React from "react";
import { createContext, useContext, useState, useCallback } from "@wordpress/element";
import { __, _n, sprintf } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";
import { usePlatform } from "@/providers/PlatformProvider";
import { chunkCounts } from "@/lib/chunk";
import { AUTO } from "@/lib/platform";
import { getSettings } from "@/lib/settings";
import type { GeneratorResult } from "@/types";


/**
 * One queued run.
 *
 * `params`, `seed` and `meta` were not here, and their absence meant "Add to batch" threw away
 * everything the page above it had been configured with: a products row queued at $200–$900 ran at the
 * schema default, and the seed and metadata switch were dropped the same way. The generate path has
 * always sent them. A queued run is the same run, deferred — anything else makes the entire parameter
 * column a control that works on one button and not on the one beside it.
 */
export interface BatchItem {
  route: string;
  count: number;
  params: Record<string, unknown>;
  seed: string;
  meta: boolean;
}

interface BatchState {
  batch: BatchItem[];
  add: (
    route: string,
    count: number,
    params: Record<string, unknown>,
    seed: string,
    meta: boolean,
  ) => void;
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

  const add = useCallback(
    (
      route: string,
      count: number,
      params: Record<string, unknown>,
      seed: string,
      meta: boolean,
    ) => {
      setBatch((bs) => {
        // Merge into an existing entry only when it is the same run. Merging on the route alone
        // summed the counts of two differently-configured queues into one row and ran whichever
        // arrived first — so queueing cheap products and then expensive ones produced twice as many
        // cheap ones. Two configurations are two jobs.
        const same = (b: BatchItem) =>
          b.route === route &&
          b.seed === seed &&
          b.meta === meta &&
          JSON.stringify(b.params) === JSON.stringify(params);

        const idx = bs.findIndex(same);

        if (idx >= 0) {
          return bs.map((b, i) => (i === idx ? { ...b, count: b.count + count } : b));
        }

        return [...bs, { route, count, params, seed, meta }];
      });
    },
    [],
  );

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
        // The same body the single-run path builds, from what the row was queued with. `include_meta`
        // was hardcoded false here and the parameters were absent entirely, so a queued run ignored
        // the page it came from.
        const body: Record<string, unknown> = {
          count: item.count,
          locale,
          include_meta: item.meta,
          // Explicit, like the single-run path. A queued run must land in the store
          // the topbar names, not wherever the server would guess.
          platform: target ?? AUTO,
          ...item.params,
        };

        if (item.seed.trim()) {
          body.seed = parseInt(item.seed, 10);
        }

        try {
          // Split, because the endpoint caps `count` at 100 and rejects anything larger outright.
          // This sent `item.count` whole, so a queued row of 250 came back as
          // `Invalid parameter(s): count` having written nothing.
          let data: GeneratorResult = {} as GeneratorResult;

          for (const chunk of chunkCounts(item.count)) {
            data = await apiFetch<GeneratorResult>({
              path: `/storeseeder/v1/${item.route}/generate`,
              method: "POST",
              data: { ...body, count: chunk },
            });
          }

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
