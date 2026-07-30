/**
 * The brand palette the listing assets are painted with.
 *
 * Mirrors `.wordpress-org/icon.svg`: one indigo lit like glass, rather than the
 * indigo-to-violet ramp the mark used to be. The icon bakes its lighting into
 * gradient stops; a banner or a screenshot frame is a much larger surface, so it
 * gets the same treatment in CSS via `glassField()`.
 *
 * Anything painting a branded surface imports from here — three copies of the
 * same hex is how the icon and the banner drifted apart in the first place.
 */
export const BRAND = {
  /** Body stops, top to bottom. Tints and shades of one hue. */
  top: '#6b68e8',
  mid: '#5250cf',
  deep: '#3b38a8',
  /** Where a thick pane would pool refracted light. */
  pool: '#a9a6ff',
  /** Lightest tint — accents that must stay legible on the body. */
  tint: '#7b78ee',
  /** Shadow colour under white cards, so shadows read as the same indigo. */
  shadow: '23, 16, 60',
} as const;

/**
 * The glass field, as stacked CSS backgrounds.
 *
 * Painted in the same order as the icon's rects — pool, specular, body — since
 * CSS draws the first background layer on top. `angle` tilts the body ramp: a
 * wide banner reads better on a diagonal, a tall frame on something closer to
 * vertical.
 */
export function glassField(angle = 160): string {
  return [
    // Light pooling low and to the right.
    `radial-gradient(60% 80% at 82% 108%, rgba(169, 166, 255, .26) 0%, rgba(169, 166, 255, 0) 70%)`,
    // Specular sweep off the top edge, spent by the top third.
    `linear-gradient(to bottom, rgba(255, 255, 255, .16) 0%, rgba(255, 255, 255, 0) 34%)`,
    // Body: one hue, tint at the top, shade at the bottom.
    `linear-gradient(${angle}deg, ${BRAND.top} 0%, ${BRAND.mid} 45%, ${BRAND.deep} 100%)`,
  ].join(', ');
}

/**
 * The white cart-and-sprout glyph, lifted from `.wordpress-org/icon.svg` without
 * its tile — a tile inside a branded field would be a panel on a panel.
 */
export function markSvg(size: number): string {
  return `<svg width="${size}" height="${size}" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <g transform="translate(11,26) scale(3.7)" fill="none" stroke="#ffffff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="8" cy="21" r="1.4"/>
    <circle cx="19" cy="21" r="1.4"/>
    <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>
  </g>
  <line x1="92" y1="40" x2="92" y2="20" stroke="#ffffff" stroke-width="2.6" stroke-linecap="round"/>
  <path d="M92,27 C86.5,21 78.5,21.5 76,27 C81.5,32 89.5,31.5 92,27 Z" fill="#ffffff"/>
  <path d="M92,22 C96,14.5 104,13.5 108.5,18.5 C104.5,25 96.5,26 92,22 Z" fill="#ffffff"/>
</svg>`;
}
