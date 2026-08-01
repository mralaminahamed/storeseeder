---
title: External services
description: What is fetched, when, and on whose say-so.
---

StoreSeeder contacts three things. **Nothing is contacted on activation, on page load, or on a schedule** — every request follows a deliberate press, and the two GitHub fetches need an administrator
to accept a consent prompt first.

No site, user or store data is ever transmitted to any of them.

## 1. GitHub — sample data

Locale vocabulary: country lists, currencies, phone and postcode patterns, product nouns, customer tags.

|              |                                                                                              |
|--------------|----------------------------------------------------------------------------------------------|
| Endpoint     | `github.com/mralaminahamed/storeseeder-sample-data-fluent-cart/archive/refs/heads/trunk.zip` |
| Triggered by | The consent prompt's approval button, or **Sync now** / **Force re-sync** in Settings        |
| Sent         | An unauthenticated `GET`. No site URL, no telemetry                                          |
| Optional     | Yes — every generator works from built-in defaults                                           |
| Filter       | `storeseeder_sample_data_source`                                                             |

## 2. GitHub — recipes

The vocabulary that makes every generator produce one coherent shop.

|              |                                                                              |
|--------------|------------------------------------------------------------------------------|
| Endpoint     | `github.com/mralaminahamed/storeseeder-recipes/archive/refs/heads/trunk.zip` |
| Triggered by | **Download the recipes** or **Sync** on the Recipes page                     |
| Sent         | An unauthenticated `GET`. About 90 KB comes back                             |
| Optional     | Yes — the rest of the plugin is unaffected                                   |
| Filter       | `storeseeder_recipes_source`                                                 |

Gated by the **same consent record** as the sample data, deliberately one rather than two: an administrator who has agreed to an outbound request to GitHub for this plugin's content has answered the
question, and asking twice for the same answer trains people to click through prompts.

Unlike the sample data, this is never fetched implicitly. It starts only when somebody presses the button, because a recipe is a feature you opt into rather than a fallback the plugin needs.

### Recipe artwork is treated as untrusted

A recipe may ship an `icon.svg`, and a site may have repointed the archive at a fork — so it is third-party markup rendering inside wp-admin. Two independent defences, because either alone has a bad
failure mode:

- Filtered server-side against a **shape-only allowlist**. An allowlist rather than a blocklist, because the set of dangerous SVG constructs is not fixed and enumerating it is a list that is wrong the
  day after it is written.
- Rendered inside an `<img>`, a passive context where script cannot run even if the filter were bypassed.

Files over 16 KB are ignored. A logo that size is not a logo.

## 3. WordPress.org — plugin directory

The **Our Plugins** page lists the author's other plugins with live ratings. Requested by the browser, only when an administrator opens that page.

## What StoreSeeder does not do

- No analytics, telemetry or usage reporting
- No licence checks or phone-home
- No outbound request on activation, on `admin_init`, or on cron
- No transmission of store, order, customer or site data anywhere

The full disclosure, including each provider's terms, is in
[`docs/guides/external-services.md`](https://mralaminahamed.github.io/storeseeder/reference/external-services/).
