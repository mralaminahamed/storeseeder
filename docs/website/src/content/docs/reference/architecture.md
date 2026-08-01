---
title: Architecture
description: How a request becomes a row.
---

```
React → REST → Controller → Generator → canonical entity → Writer → platform models
```

Four rules are load-bearing: a generator may not name a platform; money in a canonical entity is an
integer in the currency's minor unit; statuses use the canonical vocabulary; and foreign keys and
uniqueness belong to the writer, not the generator.

## Full reference

This page is a summary. The complete, canonical version lives in the repository and is what the
plugin's own contributors read:

[`docs/guides/architecture.md`](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/guides/architecture.md)

:::note[Still to port]
The repository guide is the source of truth today. Content is being moved here rather than copied —
two versions of one document is how they come to disagree.
:::
