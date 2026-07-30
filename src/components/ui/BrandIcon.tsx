import React, { useId } from "react";

export interface BrandIconProps {
  /** Rendered width and height in px. The artwork is a square. */
  size?: number;
  className?: string;
  style?: React.CSSProperties;
  /**
   * Accessible label. Omit for decorative use (the default), where the icon is
   * hidden from assistive tech because the adjacent text already names the app.
   */
  title?: string;
}

/**
 * The StoreSeeder brand mark.
 *
 * A direct port of `.wordpress-org/icon.svg` — the rounded indigo glass tile,
 * the shopping cart, and the sprout — so the admin shows the same mark that
 * WordPress.org and the plugin's own listing do. Keep the two in step: if the
 * artwork changes, change it in both places, stop values included.
 *
 * Distinct from the `storeseeder` entry in `lib/icons.tsx`, which is the flat
 * monochrome variant that inherits `currentColor` for the WordPress admin menu.
 * This one carries the brand colours and its own background.
 */
export function BrandIcon({ size = 30, className = "", style = {}, title }: BrandIconProps) {
  // Each gradient needs a document-unique id: two mounted instances sharing one
  // would collide, and the second would reference the first's definition.
  const uid = `ss-brand-${useId().replace(/:/g, "")}`;
  const body = `${uid}-body`;
  const pool = `${uid}-pool`;
  const spec = `${uid}-spec`;
  const rim = `${uid}-rim`;
  const decorative = undefined === title;

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 120 120"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={{ flexShrink: 0, display: "block", ...style }}
      role="img"
      aria-hidden={decorative || undefined}
      aria-label={decorative ? undefined : title}
    >
      {!decorative && <title>{title}</title>}

      {/* One hue lit like glass, not a blend of two brand colours: body tone, a
          specular sweep down the top third, light pooling low and to the right,
          and a rim that is bright on the top edge and bounces faintly along the
          bottom. See the source SVG for why the lighting is baked in. */}
      <defs>
        <linearGradient id={body} x1="60" y1="0" x2="60" y2="120" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#6b68e8" />
          <stop offset="0.45" stopColor="#5250cf" />
          <stop offset="1" stopColor="#3b38a8" />
        </linearGradient>

        <linearGradient id={spec} x1="60" y1="0" x2="60" y2="120" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#ffffff" stopOpacity="0.18" />
          <stop offset="0.34" stopColor="#ffffff" stopOpacity="0" />
        </linearGradient>

        <radialGradient id={pool} cx="88" cy="106" r="48" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#a9a6ff" stopOpacity="0.22" />
          <stop offset="1" stopColor="#a9a6ff" stopOpacity="0" />
        </radialGradient>

        <linearGradient id={rim} x1="60" y1="0" x2="60" y2="120" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#ffffff" stopOpacity="0.68" />
          <stop offset="0.4" stopColor="#ffffff" stopOpacity="0.04" />
          <stop offset="1" stopColor="#ffffff" stopOpacity="0.22" />
        </linearGradient>
      </defs>

      <rect width="120" height="120" rx="29" fill={`url(#${body})`} />
      <rect width="120" height="120" rx="29" fill={`url(#${pool})`} />
      <rect width="120" height="120" rx="29" fill={`url(#${spec})`} />
      <rect
        x="0.8"
        y="0.8"
        width="118.4"
        height="118.4"
        rx="28.2"
        fill="none"
        stroke={`url(#${rim})`}
        strokeWidth="1.6"
      />

      {/* Shopping cart: the store */}
      <g
        transform="translate(11,26) scale(3.7)"
        fill="none"
        stroke="#ffffff"
        strokeWidth="1.7"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <circle cx="8" cy="21" r="1.4" />
        <circle cx="19" cy="21" r="1.4" />
        <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
      </g>

      {/* Sprout: the seed being planted */}
      <line x1="92" y1="40" x2="92" y2="20" stroke="#ffffff" strokeWidth="2.6" strokeLinecap="round" />
      <path d="M92,27 C86.5,21 78.5,21.5 76,27 C81.5,32 89.5,31.5 92,27 Z" fill="#ffffff" />
      <path d="M92,22 C96,14.5 104,13.5 108.5,18.5 C104.5,25 96.5,26 92,22 Z" fill="#ffffff" />
    </svg>
  );
}
