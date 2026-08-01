---
title: Deleting generated data
description: The ledger, never a heuristic.
---

Every row StoreSeeder writes is recorded in its own ledger. Deleting the test data walks that ledger,
so it removes what StoreSeeder created and nothing that merely resembles it.

Matching on "looks like test data" would eventually delete a real catalogue on a staging site restored
from production, and that is not a mistake you can apologise your way out of.

## Full reference

This page is a summary. The complete, canonical version lives in the repository and is what the
plugin's own contributors read:

[`docs/guides/features.md`](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/guides/features.md)

:::note[Still to port]
The repository guide is the source of truth today. Content is being moved here rather than copied —
two versions of one document is how they come to disagree.
:::
