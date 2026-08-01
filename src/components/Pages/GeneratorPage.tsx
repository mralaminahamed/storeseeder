import React from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useState, useEffect } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import { generators } from "@/lib/generators";
import { fieldsFromSchema } from "@/lib/fieldsFromSchema";
import { setPath } from "@/lib/paths";
import type { ParamBag } from "@/lib/paths";
import { getSettings } from "@/lib/settings";
import { useStats } from "@/providers/StatsProvider";
import { useToast } from "@/providers/ToastProvider";
import { chunkCounts } from "@/lib/chunk";
import { useBatch } from "@/providers/BatchProvider";
import { usePlatform } from "@/providers/PlatformProvider";
import { AUTO } from "@/lib/platform";
import { ConfigColumn } from "@/components/generator/ConfigColumn";
import { PreviewTable } from "@/components/generator/PreviewTable";
import { RunBar } from "@/components/generator/RunBar";
import { Button } from "@/components/ui/button";

import type {
  GeneratorPageParams,
  GeneratorResult,
  ParameterConfig,
  ParamValue,
} from "@/types";

// ---------------------------------------------------------------------------
// Build default params from a generator's parameterConfig schema
// ---------------------------------------------------------------------------

function buildDefaultParams(
  parameterConfig: Record<string, ParameterConfig>,
): ParamBag {
  const sections = fieldsFromSchema(parameterConfig);
  let acc: ParamBag = {};

  for (const section of sections) {
    for (const f of section.fields) {
      if (f.default === undefined) {
        // For range fields use { lo: min, hi: max }
        if (f.type === "range") {
          acc = setPath(acc, f.key, { lo: f.min ?? 0, hi: f.max ?? 100 });
        } else if (f.type === "chips") {
          acc = setPath(acc, f.key, []);
        }
        // Skip other fields with no default
        continue;
      }

      if (f.type === "range") {
        // default for range is { lo, hi } derived from min/max when no explicit default
        acc = setPath(acc, f.key, f.default ?? { lo: f.min ?? 0, hi: f.max ?? 100 });
      } else if (f.type === "chips") {
        acc = setPath(acc, f.key, f.default ?? []);
      } else {
        acc = setPath(acc, f.key, f.default);
      }
    }
  }

  return acc;
}

// ---------------------------------------------------------------------------
// GeneratorPage
// ---------------------------------------------------------------------------

export default function GeneratorPage() {
  const { type } = useParams<GeneratorPageParams>();
  const navigate = useNavigate();
  const { recordRun } = useStats();
  const { toast } = useToast();
  const { add: addToBatch } = useBatch();
  const { target, ambiguous, capability, platformFields: platformFieldsFor, active, setTarget } =
    usePlatform();

  const generator = generators.find((g) => g.route === type);

  // Run/meta state — re-initialise when generator route changes
  const settings = getSettings();
  const [count, setCount] = useState<number>(settings.defaultCount);
  const [seed, setSeed] = useState<string>(settings.defaultSeed);
  const [meta, setMeta] = useState<boolean>(settings.defaultIncludeMeta);
  const locale =
    getSettings().defaultLocale ??
    window.storeseederApi?.locale?.faker ??
    "en_US";

  // Generator-specific params — re-initialise on route change
  const [params, setParams] = useState<ParamBag>(() =>
    buildDefaultParams(generator?.parameterConfig ?? {}),
  );

  // Shuffle state (incremented to reseed the preview)
  const [shuffleN, setShuffleN] = useState<number>(0);

  // Generating animation state
  const [generating, setGenerating] = useState<boolean>(false);
  const [progress, setProgress] = useState<number>(0);

  // Reset everything when generator route changes
  useEffect(() => {
    if (!generator) return;
    const s = getSettings();
    setCount(s.defaultCount);
    setSeed(s.defaultSeed);
    setMeta(s.defaultIncludeMeta);
    setParams(buildDefaultParams(generator.parameterConfig ?? {}));
    setShuffleN(0);
    setGenerating(false);
    setProgress(0);
  }, [generator?.route]); // eslint-disable-line react-hooks/exhaustive-deps

  // Redirect if generator not found
  if (!generator) {
    void navigate("/");
    return null;
  }

  // setField — immutable path update
  const setField = (key: string, value: ParamValue) => {
    setParams((prev) => setPath(prev, key, value));
  };

  // doGenerate — animate progress, POST to generate endpoint, record run
  const doGenerate = () => {
    if (generating) return;

    setGenerating(true);
    setProgress(0);

    /*
     * The requested count, split into requests the endpoint will take. `count` is capped at 100 by the
     * route's own schema and the cap is enforced during argument validation, so this used to send the
     * whole number and anything above 100 came back as `Invalid parameter(s): count` with nothing
     * written — while the stepper beside it allowed up to 100,000.
     *
     * The progress bar is driven by completed chunks now. It used to be a `requestAnimationFrame`
     * animation against a guessed duration — `1000 + min( count, 200 ) * 4` ms — which reached 100%
     * and stopped while the request was still in flight. That was survivable for one request and is
     * not for nine: a 900-row run would have shown a full bar for most of its life. The recipe runner
     * has always counted completed requests instead, for exactly this reason.
     */
    const chunks = chunkCounts(count);

    // Named rather than inlined into setTimeout: an async callback there returns a
    // promise nothing can await, so the timer would swallow a rejection.
    const finish = async () => {
      const body: Record<string, unknown> = {
        locale,
        include_meta: meta,
        // Always explicit. Letting the server fall back to auto would mean the row
        // could land somewhere other than the store named in the topbar.
        platform: target ?? AUTO,
        ...params,
      };

      if (seed.trim()) {
        body.seed = parseInt(seed, 10);
      }

      try {
        let data: GeneratorResult = {} as GeneratorResult;

        for (let i = 0; i < chunks.length; i++) {
          data = await apiFetch<GeneratorResult>({
            path: `/storeseeder/v1/${generator.route}/generate`,
            method: "POST",
            data: { ...body, count: chunks[i] },
          });

          setProgress((i + 1) / chunks.length);
        }

        recordRun(generator.route, count, true, data.message ?? "", {
          locale,
          seed,
        });
        toast(
          sprintf(
            /* translators: %1$s: count, %2$s: generator name */
            __("Generated %1$s %2$s", "storeseeder"),
            count.toLocaleString(),
            generatorLabel,
          ),
          targetLabel,
        );
      } catch (err) {
        const errMsg =
          err instanceof Error
            ? err.message
            : __("An error occurred.", "storeseeder");

        recordRun(generator.route, count, false, errMsg, { locale, seed });
        toast(__("Generation failed", "storeseeder"), errMsg);
      } finally {
        setGenerating(false);
      }
    };

    // Straight away. This used to be `setTimeout( finish, dur )`, which held the request back for the
    // length of the animation — so pressing Generate waited up to 1.8 seconds before asking for
    // anything, to keep a bar that was measuring nothing in step with a request it preceded.
    void finish();
  };

  const onAddBatch = () => {
    // Everything the Generate button would have sent. Passing only the route and the count made the
    // parameter column, the seed and the metadata switch work on one button and not the other.
    addToBatch(generator.route, count, params, seed, meta);
    toast(
      sprintf(
        /* translators: %1$s: count, %2$s: generator name */
        __("Added %1$s %2$s to batch", "storeseeder"),
        count.toLocaleString(),
        generatorLabel,
      ),
    );
  };

  const generatorLabel = generator.name.toLowerCase();

  // Which store this run lands in, named in the success toast. On a single-platform
  // site this is the only place the platform is mentioned at all.
  const targetPlatform = active.find((p) => p.id === target);
  const targetLabel = targetPlatform
    ? sprintf(
        /* translators: %s: e-commerce platform name. */
        __("Added to your %s store", "storeseeder"),
        targetPlatform.label,
      )
    : __("Added to your store", "storeseeder");

  // Two separate reasons a run cannot proceed, and they need different words: no
  // target chosen yet, versus a target that cannot represent this resource.
  const cap = capability(generator.resource);
  // The target's own parameters, merged into the form by ConfigColumn.
  const platformFields = platformFieldsFor(generator.resource);
  const unsupported = null !== cap && !cap.supported;
  const blocked = ambiguous || unsupported;

  return (
    <div className="fp-gen-main fp-enter">
      <div className="fp-gen-body">
        <div className="fp-gen-wrap">
          {/* ---- Left: config column ---- */}
          <ConfigColumn
            generator={generator}
            params={params}
            setField={setField}
            needsTarget={ambiguous}
            platforms={active}
            onPickTarget={(id) => void setTarget(id)}
            unsupported={unsupported ? cap : null}
            platformFields={platformFields}
            ignoredFields={cap?.ignored_fields ?? []}
          />

          {/* ---- Right: preview column ---- */}
          <div className="fp-preview-col">
            {/* Preview header */}
            <div className="fp-preview-head">
              <span className="fp-preview-title">
                <span className="fp-live-dot" />
                {__("Live preview", "storeseeder")}
              </span>
              <div className="fp-preview-actions">
                <Button
                  variant="ghost"
                  size="sm"
                  icon="refresh"
                  type="button"
                  onClick={() => setShuffleN((n) => n + 1)}
                >
                  {__("Shuffle", "storeseeder")}
                </Button>
              </div>
            </div>

            <p className="fp-preview-note">
              {__(
                "Sample of what this run will create — updates as you change the settings.",
                "storeseeder",
              )}
            </p>

            {/* Preview area */}
            <div
              style={{
                position: "relative",
                flex: 1,
                minHeight: 0,
                display: "flex",
              }}
            >
              <PreviewTable
                route={generator.route}
                params={params}
                count={count}
                seed={seed}
                meta={meta}
                locale={locale}
                shuffleN={shuffleN}
              />

              {/* Generating overlay */}
              {generating && (
                <div className="fp-gen-progress">
                  <div className="fp-spinner" />
                  <div style={{ fontWeight: 500, fontSize: 14 }}>
                    {sprintf(
                      /* translators: %1$s: count, %2$s: generator name */
                      __("Generating %1$s %2$s…", "storeseeder"),
                      count.toLocaleString(),
                      generatorLabel,
                    )}
                  </div>
                  <div className="fp-progress-track">
                    <div
                      className="fp-progress-fill"
                      style={{ width: `${progress * 100}%` }}
                    />
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* ---- Sticky run bar ---- */}
      <RunBar
        count={count}
        seed={seed}
        meta={meta}
        onCount={setCount}
        onSeed={setSeed}
        onMeta={setMeta}
        onGenerate={doGenerate}
        onAddBatch={onAddBatch}
        generating={generating}
        disabled={blocked}
      />
    </div>
  );
}
