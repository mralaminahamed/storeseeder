---
title: Choosing a target
description: Where the data lands, and why StoreSeeder asks rather than guesses.
---

Pick the target in the topbar, or in **Settings → This site**. The choice is stored site-wide, so two administrators cannot unknowingly seed different stores.

## Auto, and why it sometimes refuses

**Auto** resolves to the only active platform. With more than one active and none chosen, it is deliberately ambiguous and StoreSeeder asks instead of picking.

That is not caution for its own sake. Writing rows into the wrong store is the one failure nobody notices afterwards — the run succeeds, the numbers look right, and the data is in a shop you were not
testing.

Auto is a *mode*, not a name. The option stays labelled "Auto" whatever it currently resolves to; what it resolved to appears in the control's tooltip and in full on the Settings page. A menu entry
renamed after the store it happens to point at reads as a different option each time you open it, and moves under the cursor when you change which plugins are active.

## Capabilities are computed, never cached

What a platform can do is answered per request, because support is conditional: WooCommerce has no subscriptions until WooCommerce Subscriptions is active.

Caching that matrix in an option would mean activating a plugin failed to register. So an unsupported resource reports *which plugin would enable it*, and the admin says "install WooCommerce
Subscriptions" rather than dimming a tile in silence.

## Two kinds of no

A driver refusing a resource says which kind of refusal it is, because they mean different things to whoever reads them.

|                       | Means                            | Reads as   |
|-----------------------|----------------------------------|------------|
| **Unsupported**       | The platform has no such concept | A dead end |
| **Missing extension** | A plugin would enable it         | A link     |

WooCommerce records payment on the order, so there is no transaction record to create and no plugin changes that. Subscriptions are a plugin away. Reporting the first where the second is true would
send someone looking for a setting that does not exist.

## Platform-specific parameters

Some properties belong to one platform only — WooCommerce's `tax_status`, Fluent Cart's
`payment_type`. Those arrive as **generation parameters** rather than entity fields, because a field only one platform stores would make a fixed seed produce different data on the others.

Routes register before any platform is resolved, so an endpoint accepts the union of every driver's fields, and the response reports what the resolved target could not use.
