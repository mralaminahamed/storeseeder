---
title: Running a generator
description: One resource at a time, with a preview that tells the truth.
---

Pick a generator from the sidebar, set its parameters, watch the preview, press **Generate**.

![The Products generator — parameters on the left, a live preview on the right](../../../assets/screenshots/generator-products.png)

The left column is the configuration; the right is six rows built exactly the way the run will build
them. Nothing is written until you press the button in the bar along the bottom, which also carries
the count, the seed, and **Add to batch**.

## The preview is the contract

Every column the preview shows comes from the same code path the run uses. If you narrow a price range, the preview narrows. If you switch purchase history off, the customer's order count shows zero.

That sounds obvious and it was not always true: several columns used to draw from their own literal ranges, so a caller could narrow a parameter, watch the preview ignore it, and reasonably conclude
the parameter was decoration.

Four values in the preview are still invented, and only these four: the digits in a coupon code, an order number, the digits in an SKU, and an order's total. The first three have no parameter behind
them. The total is invented because a real one is the sum of the catalogue prices of the products the order points at — which is why an `order_value_range` parameter was *removed* rather than
implemented. An order line at $412 for a $19 product makes every revenue figure disagree with the store.

## A declared parameter changes the output

If a parameter appears in the admin, the REST schema, or an MCP tool's inputs, it reaches
`build_entity()` or a writer. Those three are three declarations of one contract and they are kept in step.

When a parameter cannot be honoured it is removed rather than left to look functional. Two that were removed for exactly that reason:

- `order_value_range` — see above.
- `customer_type` / `customer_distribution` — described a new-versus-existing split no writer implemented. `customer_id` replaced them, which is the part that was useful.

![The Orders generator, showing the order number, customer, item count, total and status columns](../../../assets/screenshots/generator-orders.png)

Orders are the resource where the preview earns its keep: an order's total is the sum of the catalogue
prices of the products it points at, so the columns tell you whether the shape is right before you
create nine hundred of them.

## Pinning a customer or a product

`customer_id` and `product_id` are foreign keys into your store, and they used to be number boxes — answerable only by somebody who already knew the id. They are searchable pickers now: you see a
name, the API receives an id.

The preview honours the pin. A run bound to one customer previews *that* customer rather than three invented ones, which is the opposite of what it used to say.

## The batch queue

**Add to batch** queues a generator instead of running it. The tray runs them sequentially, reporting real progress per item, and a failure in one does not abort the rest.

Useful when you want products *then* orders, and you want to walk away.

## Seeds

Set a seed and the same run produces the same data — on any platform, because a generator names no platform and a canonical entity carries no platform's own words. That is what makes the guarantee
hold across drivers rather than only within one.
