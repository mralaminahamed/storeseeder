import React from "react";
import { useEffect, useState } from "@wordpress/element";
import { decodeEntities } from "@wordpress/html-entities";
import { __, sprintf } from "@wordpress/i18n";

import { Button } from "@/components/ui/button";
import { PageHead } from "@/components/ui/PageHead";
import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";

interface WPPlugin {
  name: string;
  slug: string;
  version: string;
  short_description: string;
  icons?: { "1x"?: string; "2x"?: string; svg?: string };
  rating: number;
  num_ratings: number;
  active_installs: number;
}

export default function PluginsPage() {
  const [plugins, setPlugins] = useState<WPPlugin[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch(
      "https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[author]=mralaminahamed&request[per_page]=20",
    )
      .then((r) => r.json())
      .then((data: { plugins?: WPPlugin[] }) => {
        setPlugins(
          (data.plugins ?? []).filter(
            (p) => p.slug !== "storeseeder",
          ),
        );
        setLoading(false);
      })
      .catch(() => {
        setError(
          __(
            "Could not load plugins. Check your internet connection.",
            "storeseeder",
          ),
        );
        setLoading(false);
      });
  }, []);

  return (
    <div className="fp-page wide fp-enter">
      <PageHead
        title={__("Our Plugins", "storeseeder")}
        description={__(
          "Other plugins by the same author on WordPress.org.",
          "storeseeder",
        )}
      />

      {/* Placeholder cards in the real grid, so the layout does not jump when six
          plugins land. Uses the shared Skeleton rather than the flat grey boxes this
          page grew for itself before there was one. */}
      {loading && (
        <div className="fp-plugins-grid" data-testid="plugins-skeleton">
          <span className="sr-only" role="status">
            {__("Loading plugins…", "storeseeder")}
          </span>
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="fp-card fp-plugin-card" aria-hidden>
              <div className="fp-plugin-head">
                <Skeleton className="fp-plugin-ic" width={38} height={38} radius={10} />
                <div style={{ flex: 1 }}>
                  <Skeleton width={128} height={12} />
                  <Skeleton
                    width={84}
                    height={10}
                    style={{ marginTop: 7 }}
                  />
                </div>
              </div>
              <SkeletonText lines={2} className="fp-plugin-skel-body" />
            </div>
          ))}
        </div>
      )}

      {error && (
        <p className="fp-set-hint" style={{ color: "var(--red)" }}>
          {error}
        </p>
      )}

      {!loading && !error && plugins.length === 0 && (
        <p className="fp-sub">
          {__("No plugins found.", "storeseeder")}
        </p>
      )}

      {!loading && !error && plugins.length > 0 && (
        <div className="fp-plugins-grid">
          {plugins.map((plugin) => (
            <PluginCard key={plugin.slug} plugin={plugin} />
          ))}
        </div>
      )}
    </div>
  );
}

function Rating({ r, rc }: { r: number; rc: number }) {
  const full = "★★★★★".slice(0, r);
  const empty = "☆☆☆☆☆".slice(0, 5 - r);
  return (
    <span className="fp-rating">
      <span>
        <span className="fp-rating-stars">{full}</span>
        <span className="fp-rating-empty">{empty}</span>
      </span>
      <span className="fp-rating-count">({rc || 0})</span>
    </span>
  );
}

function PluginCard({ plugin }: { plugin: WPPlugin }) {
  const icon =
    plugin.icons?.svg ?? plugin.icons?.["2x"] ?? plugin.icons?.["1x"];
  const stars = Math.round(plugin.rating / 20);

  return (
    <div className="fp-card fp-plugin-card">
      <div className="fp-plugin-head">
        {icon ? (
          <img
            src={icon}
            alt={decodeEntities(plugin.name)}
            className="fp-plugin-ic"
            style={{ objectFit: "cover" }}
          />
        ) : (
          <span className="fp-plugin-ic" style={{ background: "var(--accent)" }}>
            {plugin.name.charAt(0)}
          </span>
        )}
        <div style={{ minWidth: 0 }}>
          <div className="fp-plugin-name">{decodeEntities(plugin.name)}</div>
          <div className="fp-plugin-ver">v{plugin.version}</div>
        </div>
      </div>

      <p className="fp-plugin-desc">
        {decodeEntities(plugin.short_description)}
      </p>

      <div className="fp-plugin-foot">
        <Rating r={stars} rc={plugin.num_ratings} />
        <span className="fp-plugin-active">
          {sprintf(
            /* translators: %s: formatted install count */
            __("%s+ active", "storeseeder"),
            plugin.active_installs.toLocaleString(),
          )}
        </span>
      </div>

      <a
        href={`https://wordpress.org/plugins/${plugin.slug}/`}
        target="_blank"
        rel="noopener noreferrer"
        className="full-w"
        style={{ display: "block" }}
      >
        <Button variant="outline" size="sm" icon="external" className="full-w" type="button">
          {__("View on WordPress.org", "storeseeder")}
        </Button>
      </a>
    </div>
  );
}
