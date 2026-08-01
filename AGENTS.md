# Agent Instructions for storeseeder

Coding style and conventions. Every rule below was checked against the source; where the
codebase is inconsistent that is stated rather than smoothed over.

**Architecture, commands, invariants and traps live in [`CLAUDE.md`](CLAUDE.md).** This file
deliberately does not repeat them — two documents describing the same architecture is how the
previous version of this file came to contradict the code.

## Related plugins

`easycommerce-fakerpress` is a **fork of this codebase** retargeted at EasyCommerce, not a
reference to follow. It shares the abstract layer, the MCP layer and `fieldsFromSchema.ts`,
and it has already drifted into real bugs — its `generators.ts` ships routes its own PHP does
not register. The plan is to absorb it as the EasyCommerce platform driver and retire it, so
do not treat its patterns as authoritative or copy decisions from it.

## Build / lint / test

Full command list and the local PHPUnit environment setup are in `CLAUDE.md`. Short version:

```bash
composer test          # needs WP_PHPUNIT__DIR, WP_DB_PASS, WP_PATH exported — see CLAUDE.md
composer phpcs         # scans includes/ only
composer phpstan
yarn build
yarn lint:js
npx tsc --noEmit       # not wired to a script, still catches real errors
```

## PHP style

- WordPress Coding Standards (WPCS), enforced by `composer phpcs`.
- PSR-4 under the `StoreSeeder\` namespace, mapped to `includes/`.
- **PHP 7.4 floor.** No union return types, `match`, enums, constructor promotion or
  `readonly`. Document `array|WP_Error` in a docblock and omit the return type, as the
  abstracts do.
- **Indent with tabs.** `.editorconfig` currently declares 4 spaces for every file; that entry
  is wrong for PHP, and the code is tab-indented throughout. Follow the code.
- **Class names are PascalCase, underscores allowed**: `Product`, `Order_Tax_Rate`,
  `Platform_Driver`, `MCP_Server`. Names like `ProductGenerator` appear only as import
  aliases, never as declarations.
- **Filenames match the class**, so also PascalCase with underscores: `Order_Tax_Rate.php`.
  This is a PSR-4/WPCS hybrid permitted by two sniff exclusions in `phpcs.xml` — do not
  "correct" them to `class-*.php`.
- **Methods and variables are `snake_case`**: `build_entity()`, `get_resource_type()`,
  `$resource_type`. Not camelCase.
- `$resource` is rejected by WPCS as a reserved name. Use `$resource_type`.
- PHPDoc on every class, method and property. Single quotes for strings unless interpolating.
- Errors are `WP_Error` with a specific code and a message that says what to do next;
  `try/catch` around anything a platform model might throw from.
- Singletons exist and are deliberate — `StoreSeeder`, `Platforms\Registry`. Everything below
  them takes its collaborators as arguments (`Resolver` accepts a `Registry`, generators are
  handed a platform, writers are handed a faker), so prefer that for new code rather than
  reaching for global state.

## TypeScript / React style

- TypeScript everywhere in `src/`. Functional components with hooks; no class components.
- **Indent with 2 spaces.**
- **Double quotes** for strings and import paths. A handful of older files use single quotes;
  match the file you are editing rather than reformatting it.
- Import order as observed: React and other external packages, then `@wordpress/*`, then local
  `@/…` paths.
- `camelCase` for variables and functions, `PascalCase` for components.
- Tailwind CSS v4 for styling. Component styles that outgrow utilities go in
  `src/components.css`, which is plain CSS scoped under `.fp-root`.
- All user-facing strings through `@wordpress/i18n`, with `sprintf` for interpolation and a
  `/* translators: */` comment for every placeholder.
- `async`/`await` with `try/catch` for API calls.
- No `console.log` in shipped code — ESLint has `no-console` at warn.
- **Font weights are round hundreds only**: 400, 500, 600, 700. No 350/450/550.
- **No literal px for padding, control height or font size in `components.css`** — read the
  tokens in `styles.css` (`--pad-*`, `--ctl-h*`, `--fs-*`, `--gap-ctl`, `--badge-h`,
  `--switch-*`). `[data-density="compact"]` overrides those tokens and nothing else, so a
  hardcoded value is a component that ignores the density setting. One-off chrome — a 1px
  rule, a dot, a knob offset — is fine.
- **New icons go in `src/lib/icons.tsx` as Lucide path data**, and one concept gets one glyph.
  A name with no entry silently renders `box`.
- **An async region shows a skeleton, not a line of text or nothing at all.** Use
  `Skeleton` / `SkeletonText` from `src/components/ui/Skeleton.tsx`, shaped like the
  content that is coming so the layout does not jump, with an `sr-only` `role="status"`
  beside it. `.fp-spinner` stays for waits with no predictable shape, such as a
  generation run. Distinguish "failed" from "still loading" — a skeleton that never
  resolves is worse than an error.

## Testing

- **PHP: PHPUnit**, under `tests/php/src/`, mirroring the `includes/` layout. New behaviour
  needs a test; a bug fix needs the test that would have caught it.
- **TypeScript units: Jest**, colocated — `src/lib/locales.test.ts` sits beside
  `src/lib/locales.ts`, so a module and its tests move and get reviewed together, and an
  untested module shows up as a missing neighbour. Run with `yarn test:unit`.
- **Components: React Testing Library**, in the same colocated Jest files
  (`src/components/**/X.test.tsx`). Query by role and accessible name — `getByRole("switch",
  { name: /Include metadata/ })` — not by class: a test that reads the DOM the way a user
  does catches the accessibility regressions a snapshot never will. DOM matchers come from
  `@testing-library/jest-dom/jest-globals`, which is the entry point that types `expect` as
  imported from `@jest/globals`.
- **Browser: Playwright**, under `tests/e2e/`, for whole flows against a real WordPress.
- **File naming is load-bearing**: Jest collects `*.test.ts(x)`, Playwright collects
  `*.spec.ts`. Cross them and each runner tries to execute the other's files.
- Jest transpiles through Babel and does **not** type-check. `npx tsc --noEmit` is the only
  type-check the tests get, so run it as part of the same pass.
- Import test helpers from `@jest/globals` rather than relying on ambient globals — that is
  what keeps the tests type-checked without an `@types/jest` dependency.
- `tests/e2e/setup.sh` resets the admin password. Never run it, or `yarn test:e2e`, against a
  site whose credentials matter without asking first.
- Baselines: **345 PHP tests / 1927 assertions**, **198 Jest tests**. A refactor claiming no
  behaviour change must return those numbers identically, not merely pass.

## General

- Conventional commits: `type(scope): description`. Explain *why* in the body, not what the
  diff already shows.
- Prefer `const`; arrow functions for callbacks.
- Keep functions and components small and single-purpose; extract shared logic to `src/lib/`
  or a PHP abstract rather than duplicating it.
- Update `README.md` for user-visible changes, `CHANGELOG.md` for releases, and
  `docs/guides/external-services.md` plus `readme.txt` together whenever outbound-request behaviour
  changes — those two have contradicted each other before.
- Match the surrounding code's comment density. Comments here explain *why* a value or
  workaround exists, and several encode schema facts learned by debugging, so do not delete
  them when moving code.

## Adding a generator

Five pieces, listed with paths in `CLAUDE.md`. The rule worth repeating: **a generator may not
name a platform** — no models, no table names, no platform status strings, no database reads.
Persistence belongs to a writer.
