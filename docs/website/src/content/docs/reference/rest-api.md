---
title: REST API
description: Every generator, plus recipes and the ledger.
---

All routes live under `/wp-json/storeseeder/v1/` and are gated by the same capability as the admin page — `StoreSeeder\Access`, not a literal `manage_options`, so a site that grants the routes but not
the page has a broken plugin rather than a subtly different one.

## Per generator

```http
POST /storeseeder/v1/{base}/preview
POST /storeseeder/v1/{base}/generate
```

`preview` touches nothing. `generate` writes, and reports what it created.

:::caution[Bases are not resource names]
`cart_session` is served at `cart-sessions`; `tax_class` at `tax_classes`. No singularisation rule survives `shipping_classes`, so generators carry an explicit `resource` alongside their `route` and
neither is derived from the other.
:::

### Base parameters

| Parameter    | Notes                                                                                               |
|--------------|-----------------------------------------------------------------------------------------------------|
| `count`      | 1–100. A larger run is many requests, which is also what makes progress honest                      |
| `locale`     | Enumerated from the one locale list, not a hardcoded subset                                         |
| `seed`       | Same seed, same data, on any platform                                                               |
| `platform`   | Not enumerated — the driver set is filterable, so an enum would reject a valid third-party platform |
| `recipe`     | Which vocabulary to use. An unknown id is ignored rather than rejected                              |
| `recipe_run` | Groups the ledger rows a run writes, so it can be undone as a unit                                  |

Resource-specific parameters are documented by the endpoint's own schema.

## Recipes

```http
GET  /storeseeder/v1/recipes
POST /storeseeder/v1/recipes/sync
```

`GET` returns each manifest with its plan **annotated against the resolved target** — whether that platform supports each resource, and the driver's own reason when it does not. That annotation is per
request rather than baked into the manifest, because support is a property of the request: whether a resource can be created depends on which store you are writing to and which plugins are active.

`POST /sync` downloads the archive. It is a write rather than a query parameter on the read, because a
`GET` that fetches 90 KB from GitHub is a `GET` a browser prefetcher will fire on its own.

## Existing records

```http
GET /storeseeder/v1/lookup?resource=product&search=oat
```

What the entity pickers read, through the resolved platform's own driver. A driver that cannot search returns nothing, and the control falls back to accepting a typed id — a store whose driver cannot
search must not become a store you cannot target.

## The ledger

```http
GET    /storeseeder/v1/generated
DELETE /storeseeder/v1/generated
```

`DELETE` accepts `resource`, `limit`, `run_id`, and `forget`. Deletion is batched; call until
`remaining` reaches zero.

`forget` drops the records without touching the store, for a ledger that no longer matches reality. Named for what it does, because forgetting and deleting are opposite mistakes to make.

## Platforms

```http
GET /storeseeder/v1/platforms
```

Which drivers exist, which are active, and the full capability matrix — computed per request, never cached, so activating a plugin takes effect immediately.
