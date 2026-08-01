---
title: Introduction
description: What StoreSeeder is, and what it is for.
---

StoreSeeder generates realistic test data for WordPress e-commerce stores. Two ways in:

- **A recipe** builds a whole coherent shop in one click — a corner grocer, a fashion boutique, a
  home & garden store — across nine resources in dependency order.
- **A generator** builds one resource at a time, with a live preview and parameters that reach the
  output.

It is not single-platform. A **platform driver** decides where data lands and the same twenty-one
generators feed every driver, so a fixed seed produces the same data wherever it is written. Fluent
Cart and WooCommerce ship today.

:::caution
StoreSeeder writes large volumes of test data directly into your store. Use it on development or
staging sites, and back up your database before generating large datasets.
:::

## Full reference

This page is a summary. The complete, canonical version lives in the repository and is what the
plugin's own contributors read:

[`docs/guides/features.md`](https://github.com/mralaminahamed/storeseeder/blob/trunk/docs/guides/features.md)

:::note[Still to port]
The repository guide is the source of truth today. Content is being moved here rather than copied —
two versions of one document is how they come to disagree.
:::
