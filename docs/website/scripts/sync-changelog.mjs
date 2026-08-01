/**
 * Generate the site's changelog page from the repository's own CHANGELOG.md.
 *
 *   node scripts/sync-changelog.mjs
 *
 * Chained into `dev` and `build` with `&&`, so the page cannot be stale in either the dev server or a
 * deploy. Not as `predev`/`prebuild`: Yarn Berry dropped implicit pre/post script execution, so those
 * are simply never called and the first build after adding them failed with
 * `The slug "reference/changelog" ... does not exist`.
 *
 * Copied rather than hand-written because a second changelog is a second changelog: the root file is
 * the canonical record, `readme.txt` carries the four most recent releases for the plugin directory,
 * and a third transcription maintained by hand would start agreeing with neither. The output is
 * gitignored for the same reason — the moment it is committed, somebody edits it there.
 *
 * Two rewrites happen on the way through:
 *
 * - The top-level `# Changelog` heading is dropped. Starlight renders the title from frontmatter, so
 *   keeping it would print the word twice and put an `<h1>` inside the page body.
 * - Repository-relative links (`readme.txt`, `docs/guides/…`) are repointed at GitHub. They resolve
 *   from the repository root and would 404 under `/storeseeder/reference/changelog/`.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const HERE = dirname(fileURLToPath(import.meta.url))
const REPO = join(HERE, '..', '..', '..')
const SOURCE = join(REPO, 'CHANGELOG.md')
const OUT = join(HERE, '..', 'src', 'content', 'docs', 'reference', 'changelog.md')

const BLOB = 'https://github.com/mralaminahamed/storeseeder/blob/trunk/'

/*
 * No "generated file, do not edit" banner in the body.
 *
 * This is Markdown, not MDX, so a `{/* … *\/}` block is not a comment — it is literal text, and it
 * printed at the top of the published page. An HTML comment would have been invisible but pointless:
 * the file is gitignored, so the only reader who could be warned off editing it is one who has already
 * gone looking for a file that is not in the repository. The frontmatter's own description says where
 * the content comes from.
 */
const FRONTMATTER = `---
title: Changelog
description: Every release and what changed in it. Generated from the repository's own CHANGELOG.md.
tableOfContents:
  maxHeadingLevel: 2
---

`

function main() {
  let markdown

  try {
    markdown = readFileSync(SOURCE, 'utf8')
  } catch (error) {
    // Loud, not silent. A missing source here means the site would deploy without a changelog, and a
    // page quietly absent is harder to notice than a build that stopped.
    console.error(`sync-changelog: cannot read ${SOURCE}\n${error.message}`)
    process.exit(1)
  }

  const body = markdown
    // The `# 📋 Changelog` heading and the paragraph immediately explaining the file's role: both are
    // about the repository file rather than this page, and the page says the same thing in its own
    // description.
    .replace(/^#\s+.*\n+/, '')
    // `[text](readme.txt)` and `[text](docs/guides/x.md)` → absolute. Anything already absolute, an
    // anchor, or a mailto is left alone.
    .replace(/\]\((?!https?:|#|mailto:)([^)]+)\)/g, (_match, path) => `](${BLOB}${path})`)
    .trimStart()

  mkdirSync(dirname(OUT), { recursive: true })
  writeFileSync(OUT, FRONTMATTER + body, 'utf8')

  const releases = (body.match(/^## \[/gm) ?? []).length

  console.log(`sync-changelog: wrote ${releases} release${1 === releases ? '' : 's'} to ${OUT}`)
}

main()
