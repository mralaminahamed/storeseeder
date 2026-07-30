# WordPress.org Plugin Assets

This directory contains assets for the WordPress.org plugin repository.

## Required Assets

### Banner Images
- `banner-1544x500.png` - Plugin banner (1544x500px)
- `banner-772x250.png` - Plugin banner small (772x250px)

### Icon Images
- `icon-128x128.png` - Plugin icon (128x128px)
- `icon-256x256.png` - Plugin icon high-res (256x256px)
- `icon-512x512.png` - Plugin icon ultra-high-res (512x512px)

### Screenshots
- `screenshot-1.png` - Main admin interface
- `screenshot-2.png` - Product generator
- `screenshot-3.png` - Customer generator
- `screenshot-4.png` - Order generator (if applicable)
- `screenshot-5.png` - Settings/configuration (if applicable)

## Guidelines

- All images should be in PNG format
- Screenshots should be 1200x900px minimum
- Icons should have transparent backgrounds
- Banners should work on light and dark backgrounds
- File names should follow the exact naming convention above

## Generation

Every asset here is generated from a source in the repo — none are hand-drawn, so
a palette change means re-running a command rather than redrawing by eye. All
three share one palette: `tests/e2e/brand.ts`.

**Icons** — rasterised from `icon.svg`, which carries the artwork and its baked
glass lighting:

```sh
for s in 128 256 512; do rsvg-convert -w $s -h $s icon.svg -o "icon-${s}x${s}.png"; done
```

`src/admin/components/ui/BrandIcon.tsx` is a hand-kept port of the same SVG for
the admin UI. Change one, change the other; `tests/php/src/AdminMenuIconTest.php`
catches drift in the glyph, not in the colours.

**Banners** — composed from markup in `tests/e2e/specs/banners.spec.ts`. One
layout in 772x250 units, scaled for the retina size, so the two files cannot
disagree. Needs no WordPress:

```sh
npm run test:e2e:banners
```

**Screenshots** — real captures of the plugin, inset in a branded frame by
`tests/e2e/specs/screenshots.spec.ts`. This one does need a WordPress install with
the plugin active, Fluent Cart present, and credentials in `tests/e2e/.env.test`:

```sh
npm run test:e2e:screenshots
```