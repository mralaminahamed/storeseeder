# StoreSeeder documentation site

[Astro](https://astro.build) + [Starlight](https://starlight.astro.build), published to GitHub Pages
at `https://mralaminahamed.github.io/storeseeder`.

```bash
cd docs/website
yarn install
yarn dev        # http://localhost:4321/storeseeder
yarn build      # → dist/
```

## Where the content lives

`src/content/docs/**` — one Markdown file per page, matched to the `sidebar` in `astro.config.mjs`.
A slug in that sidebar with no file fails the build, which is the intended behaviour: a nav entry
pointing at nothing is worse than a missing entry.

## The relationship with `docs/guides/`

`docs/guides/*.md` is the canonical documentation today and the site's pages summarise it, each
linking back to its source.

**This is deliberately a one-way arrangement, not a copy.** Two versions of one document is how they
come to disagree, and the disagreement is always discovered by a reader rather than by a test. As
content is written up here it should be *moved*, leaving the guide as a pointer — not duplicated.

If that decision is ever reversed and the guides become canonical for good, the honest fix is to have
this site read them directly rather than restate them.

## Do not delete `.astro/` while `yarn dev` is running

The dev server watches it. Removing it mid-run makes Astro restart, fail to import
`.astro/content-assets.mjs`, and then report the content collection as empty — after which every
request logs `The slug "…" specified in the Starlight sidebar config does not exist` for pages that
exist perfectly well on disk.

Nothing is actually broken at that point and no amount of editing fixes it. Stop the server and start
it again.

If you do need to clear the cache, stop `yarn dev` first.

## When a page renders a title and no content

Almost always a malformed `:::` directive. Starlight needs the content on the line *below* the
opener:

```md
:::note
Content here.
:::
```

With the text on the opener line instead, the **whole document body** renders as nothing — the page
still builds and still appears in the nav. `yarn dev` gives the real error where `yarn build` does
not:

```
[ERROR] [starlight-docs-loader] Error rendering guides/recipes.md: node.children is not iterable
```

Something in this repository reflows long lines in Markdown files, which is how two pages came to
have collapsed directives at once. If you have Markdown reformat-on-save enabled, that is the
suspect.

## Adding a page

1. Write `src/content/docs/<section>/<slug>.md` with `title` and `description` front matter.
2. Add it to the matching `sidebar` group in `astro.config.mjs`.
3. `yarn build` — it will tell you if the two disagree.

## Images

Both are generated, never hand-placed:

```bash
yarn shots:docs          # every admin screenshot, from a real install
yarn shots:docs-banner   # the hero banner
```

The screenshots are raw captures — no branding plate, unlike the WordPress.org set, because here the
caption is the prose beside the image.

The banner is composed from markup in `tests/assets/docs-banner.spec.ts`, from the same
`tests/assets/brand.ts` the plugin icon and the listing banners use. It deliberately carries **no
wordmark and no tagline**: the hero already prints both, and an image that repeats them says
everything twice. It shows the idea instead — three recipes becoming a filled catalogue.

## Diagrams

`astro-mermaid` is installed, so a ```mermaid fence renders. It follows the site's light/dark theme
automatically.
