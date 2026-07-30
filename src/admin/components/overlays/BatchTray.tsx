import React from "react";
import { useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import { useBatch } from "@/admin/providers/BatchProvider";
import { generators } from "@/admin/lib/generators";
import { Button } from "@/admin/components/ui/button";
import { Stepper } from "@/admin/components/generator/fields/Stepper";
import { Icon } from "@/admin/lib/icons";

interface BatchTrayProps {
  onClose: () => void;
}

export function BatchTray({ onClose }: BatchTrayProps) {
  const { batch, setCount, remove, runAll } = useBatch();
  const [running, setRunning] = useState(false);
  const [prog, setProg] = useState(0);

  const total = batch.reduce((s, b) => s + b.count, 0);

  const run = async () => {
    if (running || batch.length === 0) return;
    setRunning(true);
    setProg(0);
    const n = batch.length;
    await runAll((done) => setProg(done / n));
    setRunning(false);
  };

  return (
    <>
      <div
        className="fp-overlay"
        style={{ background: "color-mix(in oklch,#000 30%,transparent)" }}
        role="presentation"
        onMouseDown={onClose}
      />
      <aside className="fp-batch" data-testid="batch-tray">
        <div className="fp-tweaks-head">
          <Icon name="layers" size={17} />
          <span className="fp-tweaks-title">
            {__("Batch queue", "storeseeder")}
          </span>
          <span className="fp-badge tone-accent" style={{ marginLeft: 4 }}>
            {batch.length}
          </span>
          <button
            type="button"
            className="fp-icon-btn"
            style={{ marginLeft: "auto", width: 30, height: 30, border: "none" }}
            aria-label={__("Close", "storeseeder")}
            onClick={onClose}
          >
            <Icon name="x" size={17} />
          </button>
        </div>

        <div className="fp-batch-body">
          {batch.length === 0 ? (
            <div className="fp-empty" style={{ padding: 40 }}>
              <Icon name="layers" size={24} />
              <div>{__("Queue is empty.", "storeseeder")}</div>
              <div style={{ fontSize: 12 }}>
                {__(
                  "Add generators with “Add to batch” to run them together.",
                  "storeseeder",
                )}
              </div>
            </div>
          ) : (
            batch.map((b, i) => {
              const g = generators.find((x) => x.route === b.route);
              return (
                <div key={b.route} className="fp-batch-item">
                  <span className="fp-batch-ic">
                    <Icon name={g?.iconName ?? "box"} size={17} />
                  </span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 500 }}>
                      {g?.name ?? b.route}
                    </div>
                    <div style={{ fontSize: 12, color: "var(--text-3)" }}>
                      {sprintf(
                        /* translators: %s: item count */
                        __("%s items", "storeseeder"),
                        b.count.toLocaleString(),
                      )}
                    </div>
                  </div>
                  <Stepper
                    value={b.count}
                    onChange={(v) => setCount(i, v)}
                    min={1}
                    max={100000}
                  />
                  <button
                    type="button"
                    className="fp-icon-btn"
                    style={{ width: 32, height: 32 }}
                    aria-label={__("Remove", "storeseeder")}
                    onClick={() => remove(i)}
                  >
                    <Icon name="trash" size={15} />
                  </button>
                </div>
              );
            })
          )}
        </div>

        <div className="fp-batch-foot">
          {running && (
            <div className="fp-batch-progress">
              <div style={{ width: `${prog * 100}%` }} />
            </div>
          )}
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              fontSize: 13,
            }}
          >
            <span style={{ color: "var(--text-3)" }}>
              {__("Total", "storeseeder")}
            </span>
            <span style={{ fontWeight: 600 }}>
              {sprintf(
                /* translators: %s: total item count */
                __("%s items", "storeseeder"),
                total.toLocaleString(),
              )}
            </span>
          </div>
          <Button
            variant="primary"
            size="lg"
            icon="sparkles"
            className="full-w"
            onClick={() => void run()}
            disabled={running || batch.length === 0}
            type="button"
          >
            {running
              ? __("Generating…", "storeseeder")
              : sprintf(
                  /* translators: %s: number of queued generators */
                  __("Run batch (%s)", "storeseeder"),
                  batch.length.toLocaleString(),
                )}
          </Button>
        </div>
      </aside>
    </>
  );
}
