## Summary

<!-- What does this change and why? One or two paragraphs. -->

Closes #

## Type of Change

- [ ] Bug fix
- [ ] New feature or generator
- [ ] Refactor (no behavior change)
- [ ] Documentation
- [ ] Build, tooling, or release

## How I Verified It

<!-- Commands you ran and what you exercised in the admin. Be specific about the generator,
     parameters, and store state. -->

```
composer lint
composer analyse
composer test
yarn build
```

Manual check:

## Checklist

- [ ] Branched off `trunk`, commits follow `type(scope): summary`
- [ ] `composer lint`, `composer analyse`, and `composer test` pass
- [ ] `yarn build` compiles
- [ ] New PHP has PHPDoc; REST routes validate input against JSON Schema and gate on `StoreSeeder\Access`
- [ ] New user-facing strings are translatable; `composer makepot` re-run if strings changed
- [ ] Docs updated where relevant (`README.md`, `docs/`, `CHANGELOG.md`)
- [ ] Any new outbound HTTP request is documented in `docs/external-services.md`, and summarised in
      `README.md` and `readme.txt`
- [ ] No `console.log` or debug output left behind

## Not Tested / Known Gaps

<!-- Say plainly what you did not cover. This is more useful than an empty section. -->
