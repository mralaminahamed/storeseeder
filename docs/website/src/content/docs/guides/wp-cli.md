---
title: WP-CLI
description: Every surface the admin has, from the command line.
---

```bash
wp storeseeder recipe list
wp storeseeder recipe run grocery --size=small
wp storeseeder generate products --count=50
wp storeseeder cleanup --run_id=rcp_grocery_ab12cd
```

Commands dispatch through the same controllers as the REST API, so there is one implementation rather
than two that can disagree.

## Full reference

This page is a summary. The complete, canonical version lives in the repository and is what the
plugin's own contributors read:

[`docs/guides/usage.md`](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/guides/usage.md)

:::note[Still to port]
The repository guide is the source of truth today. Content is being moved here rather than copied —
two versions of one document is how they come to disagree.
:::
