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

## Adding a page

1. Write `src/content/docs/<section>/<slug>.md` with `title` and `description` front matter.
2. Add it to the matching `sidebar` group in `astro.config.mjs`.
3. `yarn build` — it will tell you if the two disagree.

## Diagrams

`astro-mermaid` is installed, so a ```mermaid fence renders. It follows the site's light/dark theme
automatically.
