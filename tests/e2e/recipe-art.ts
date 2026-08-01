/**
 * The three shipped recipes, as artwork.
 *
 * Both banners draw recipe cards now — the documentation site's hero and the WordPress.org listing's —
 * so the icons, the names, the colours and the row counts live here rather than in each of them. Two
 * copies of a recipe's identity is how one banner ends up green and the other teal.
 *
 * The icons are copied verbatim from `<recipe>/icon.svg` in the storeseeder-recipes archive, which is
 * the same file the admin renders beside the same name. `currentColor` throughout, so one declaration
 * per card colours every stroke and the 14%-opacity wash behind them.
 *
 * The counts are the manifests' own totals at Medium. They are quoted on public artwork, so they are
 * worth re-reading against `recipes.json` when a manifest changes.
 */

type Slug = 'grocery' | 'fashion' | 'home-garden';

/** The path data, without a wrapping `<svg>` — `recipeIcon()` adds that at the size asked for. */
const PATHS: Record<Slug, string> = {
  grocery: `
    <rect x="6" y="16" width="36" height="26" rx="5" fill="currentColor" opacity=".14"/>
    <path d="M12 16 15 8h18l3 8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M6.5 16h35a1 1 0 0 1 1 1.1l-2.3 22a4 4 0 0 1-4 3.6H11.8a4 4 0 0 1-4-3.6l-2.3-22A1 1 0 0 1 6.5 16Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M18 24v5a6 6 0 0 0 12 0v-5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>`,
  fashion: `
    <path d="M18 8h12l11 7-4.5 8L33 21v19H15V21l-3.5 2L7 15Z" fill="currentColor" opacity=".14"/>
    <path d="M18 8h12l11 7-4.5 8L33 21v19H15V21l-3.5 2L7 15Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M18 8a6 6 0 0 0 12 0" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>`,
  'home-garden': `
    <path d="M24 6 43 21v19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V21Z" fill="currentColor" opacity=".14"/>
    <path d="M5 21 24 6l19 15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M9 19v21a2 2 0 0 0 2 2h26a2 2 0 0 0 2-2V19" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M24 42V30c-4 0-6-2.4-6-5.5S20 19 24 19s6 2.4 6 5.5S28 30 24 42Z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>`,
};

/** One recipe's icon at a given pixel size. */
export function recipeIcon(slug: Slug, size: number): string {
  return `<svg viewBox="0 0 48 48" width="${size}" height="${size}" aria-hidden="true">${PATHS[slug]}</svg>`;
}

export interface RecipeArt {
  slug: Slug;
  name: string;
  /** The icon's own colour, as the archive ships it. Tuned for a white card. */
  colour: string;
  /** Total rows at Medium, formatted. */
  rows: string;
  /** Three product names from that recipe's vocabulary, for a card with room for them. */
  items: readonly string[];
}

/** In the order the archive lists them. */
export const RECIPES: readonly RecipeArt[] = [
  {
    slug: 'grocery',
    name: 'Corner grocer',
    colour: '#16a34a',
    rows: '1,794',
    items: ['Organic Rolled Oats', 'Salt-Cured Basmati Rice', 'Cheddar Wedge'],
  },
  {
    slug: 'fashion',
    name: 'Fashion boutique',
    colour: '#7c3aed',
    rows: '3,466',
    items: ['Boucle Trench Coat', 'Cashmere Jumper', 'Chelsea Boots'],
  },
  {
    slug: 'home-garden',
    name: 'Home & garden',
    colour: '#d97706',
    rows: '1,225',
    items: ['Solid Oak Dining Table', 'Rattan Armchair', 'Cast Iron Planter'],
  },
] as const;
