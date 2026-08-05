import React from "react";
import apiFetch from "@wordpress/api-fetch";
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
  description: string;
  icon: string;
  /** Out of five; the server converts the directory's out-of-100 score. */
  rating: number;
  num_ratings: number;
  active_installs: number;
  state: "active" | "inactive" | "missing";
  /** Core's own install/activate URL, or "" when the user may not do it. */
  action_url: string;
  url: string;
}

export default function PluginsPage() {
  const [plugins, setPlugins] = useState<WPPlugin[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /*
   * Asked of this plugin rather than of api.wordpress.org directly.
   *
   * The browser cannot know what the site already has installed, and calling
   * the directory on every paint re-fetches a list that changes about as often
   * as a release. The server caches it for twelve hours and answers with the
   * state of each plugin here.
   */
  useEffect(() => {
    let cancelled = false;

    apiFetch<{ plugins?: WPPlugin[]; error?: string }>({
      path: "/storeseeder/v1/plugins",
    })
      .then((data) => {
        if (cancelled) return;
        setPlugins(data.plugins ?? []);
        if (data.error) {
          setError(
            __(
              "The plugin directory could not be reached, so this list may be incomplete.",
              "storeseeder",
            ),
          );
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError(
            __(
              "Could not load plugins. Check your internet connection.",
              "storeseeder",
            ),
          );
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
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
  const icon = plugin.icon;
  /*
   * Already out of five. The directory scores out of a hundred and the server
   * divides on the way out, so dividing again here flattens every rating to
   * nought stars.
   */
  const stars = Math.round(plugin.rating);

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
        {decodeEntities(plugin.description)}
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

      {/*
        Three states, not two. "Installed but switched off" is the one worth
        telling apart: the site has the plugin and needs a click, not a
        download.

        The links are core's own update.php and plugins.php with core's own
        nonces, built server-side — and empty for anybody without the
        capability, so what renders is the plugin without a button rather than
        a button that refuses.
      */}
      {plugin.state === "active" && (
        <Button variant="outline" size="sm" className="full-w" type="button" disabled>
          {__("Active", "storeseeder")}
        </Button>
      )}

      {plugin.state === "inactive" && plugin.action_url && (
        <a href={plugin.action_url} className="full-w" style={{ display: "block" }}>
          <Button variant="primary" size="sm" className="full-w" type="button">
            {__("Activate", "storeseeder")}
          </Button>
        </a>
      )}

      {plugin.state === "missing" && plugin.action_url && (
        <a href={plugin.action_url} className="full-w" style={{ display: "block" }}>
          <Button variant="primary" size="sm" className="full-w" type="button">
            {__("Install now", "storeseeder")}
          </Button>
        </a>
      )}

      {(plugin.state === "active" || !plugin.action_url) && (
        <a
          href={plugin.url}
          target="_blank"
          rel="noopener noreferrer"
          className="full-w"
          style={{ display: "block" }}
        >
          <Button variant="outline" size="sm" icon="external" className="full-w" type="button">
            {__("View on WordPress.org", "storeseeder")}
          </Button>
        </a>
      )}
    </div>
  );
}
