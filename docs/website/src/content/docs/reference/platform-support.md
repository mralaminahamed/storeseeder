---
title: Platform support
description: What each driver can write, and what it refuses — with reasons.
---

Support is **computed per request**, never cached. Caching it in an option would mean activating a plugin failed to register — so this table is what the shipped drivers answer today, and the plugin's
own **Settings → Platforms** is always authoritative for your site.

## The matrix

| Resource          | Fluent Cart                                      | WooCommerce                              |
|-------------------|--------------------------------------------------|------------------------------------------|
| Product           | ✅ except `backorders`                           | ✅                                       |
| Product variation | ✅                                               | ✅                                       |
| Product category  | ✅                                               | ✅                                       |
| Product tag       | ❌ no tag taxonomy                               | ✅                                       |
| Brand             | ✅                                               | ✅                                       |
| Attribute         | ✅                                               | ✅                                       |
| Product download  | ✅                                               | ✅                                       |
| Customer          | ✅                                               | ✅                                       |
| Order             | ✅ except `company`                              | ✅                                       |
| Order tax rate    | ✅                                               | ✅                                       |
| Transaction       | ✅                                               | ❌ recorded on the order                 |
| Refund            | ✅                                               | ✅                                       |
| Coupon            | ✅ except `maximum_amount`, `exclude_sale_items` | ✅ except `starts_at`                    |
| Cart session      | ✅                                               | ✅                                       |
| Shipping class    | ✅                                               | ✅                                       |
| Shipping plan     | ✅                                               | ✅ except `delivery_min`, `delivery_max` |
| Tax class         | ✅                                               | ✅                                       |
| Subscription      | ✅                                               | 🔌 WooCommerce Subscriptions             |
| License           | ✅                                               | ❌                                       |
| Label             | ✅                                               | ❌                                       |
| Log               | ✅                                               | ❌                                       |

✅ supported · ❌ the platform has no such concept · 🔌 a plugin would enable it

## Three different noes

The distinction is deliberate, because they read differently to whoever hits them.

**`unsupported`** — the platform has no such concept, and no plugin changes that. WooCommerce records payment on the order, so there is no transaction record to create. A dead end, and saying "install
something" would send someone looking for a setting that does not exist.

**`missing_extension`** — a plugin would enable it. Subscriptions on WooCommerce. A link, not a dead end.

**`supported_except`** — the resource is written, but one canonical field cannot be stored. Fluent Cart has no `company` column on an order address, so the admin prints which field is dropped rather
than dropping it silently.

:::note[Not for a field whose intent was met]
`supported_except` is for a field a *platform* cannot store — not one nothing implements, and not one whose intent was satisfied another way.

Fluent Cart's `shipping_total` is `NOT NULL`, so it cannot distinguish an unshipped order from a free-shipped one. But switching shipping off still charges nothing, so listing the field would tell a
caller their request was dropped when it was not. A false alarm is worse than the lost nuance.
:::

## A claim without a writer is reported

A driver that declares support and ships no writer reports `storeseeder_missing_writer` at generate time — once, rather than failing per item — because the matrix and `writer_classes()` have to agree
and only one of them is checkable at boot.

## Adding a driver

See [Extension points](/storeseeder/reference/extension-points/). `storeseeder_platforms` is the whole surface: the generators, REST API, CLI and admin all pick a new driver up without changes.
