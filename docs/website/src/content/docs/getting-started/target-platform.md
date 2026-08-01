---
title: Choosing a target
description: Where the data lands, and why StoreSeeder asks rather than guesses.
---

Which store the data goes into is a choice, not a build-time assumption. Pick it in the topbar or in
Settings; the decision is stored site-wide so two administrators cannot unknowingly seed different
stores.

With more than one platform active and none chosen, **Auto** is deliberately ambiguous and
StoreSeeder asks. Guessing which store to write into is the one failure nobody notices afterwards.

## Full reference

This page is a summary. The complete, canonical version lives in the repository and is what the
plugin's own contributors read:

[`docs/guides/usage.md`](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/guides/usage.md)

:::note[Still to port]
The repository guide is the source of truth today. Content is being moved here rather than copied —
two versions of one document is how they come to disagree.
:::
