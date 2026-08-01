---
title: WP-CLI
description: Every surface the admin has, from the command line.
---

Commands dispatch through the same controllers as the REST API. One implementation rather than two that can disagree.

## Recipes

```bash
wp storeseeder recipe list
wp storeseeder recipe run grocery
wp storeseeder recipe run fashion --size=small --platform=woocommerce --locale=de_DE
```

`run` walks the same ordered plan the admin does, chunked at the endpoint's cap, and prints the run id to undo with. If the recipe ships no vocabulary for the locale you asked for, it warns before
doing the work rather than after.

## Generating

```bash
wp storeseeder generate products --count=50
wp storeseeder generate orders --count=200 --seed=42
wp storeseeder preview customers --count=5
```

Resource-specific parameters work too:

```bash
wp storeseeder generate products --count=20 --product_type=digital
```

:::note[Flags must be declared]
WP-CLI rejects any flag a command's synopsis does not declare, silently — it does not warn, the value simply never arrives. Resource-specific parameters reach the plugin through a `generic` synopsis
entry.
:::

## Inspecting

```bash
wp storeseeder platforms          # which drivers are active, and what each supports
wp storeseeder locales            # the 75 generatable locales
wp storeseeder sample-data        # status of the optional vocabulary
```

## Cleaning up

```bash
wp storeseeder cleanup                                   # what is recorded
wp storeseeder cleanup delete --yes                      # everything StoreSeeder made
wp storeseeder cleanup delete --resource=product --yes    # one resource
wp storeseeder cleanup --run_id=rcp_grocery_ab12cd --yes  # one recipe run
```

Without `--yes` it asks, naming what it is about to remove.

## Consent still applies

`wp storeseeder sample-data sync` refuses unless an administrator has already accepted the consent prompt in the admin. Nothing on the command line can stand in for that: the prompt is the only thing
that grants permission, and a command that quietly fetched from GitHub would make the disclosure in
`readme.txt` untrue.

What the command adds is the ability to *act on* a decision already made, which is what a deploy script needs.
