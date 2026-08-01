---
title: Extension points
description: Adding a platform, a recipe, or a resource — without patching the plugin.
---

## A whole platform

`storeseeder_platforms` is the entire surface needed. Append an object implementing
`Platform_Interface` — extending `Platform_Driver` is shortest — and the generators, the REST API, the CLI and the admin all pick it up.

```php
add_filter( 'storeseeder_platforms', function ( array $platforms ): array {
    $platforms[] = new My_Store_Driver();

    return $platforms;
} );
```

A driver declares which resources it supports and, when it refuses one, which *kind* of refusal it is:

```php
Capability::unsupported( 'This platform records payment on the order.' );
Capability::missing_extension( 'my-subs', 'My Subscriptions' );
Capability::supported_except( array( 'company' ) );
```

`supported_except()` is for a canonical field the platform cannot store — not for one nothing implements, and not for one whose *intent* was met by other means. A false alarm is worse than the lost
nuance.

## A recipe

Two filters, and no download involved:

```php
add_filter( 'storeseeder_recipes', function ( $manifests ) {
    $manifests[] = json_decode( file_get_contents( __DIR__ . '/my-recipe/recipe.json' ), true );

    return $manifests;
} );

add_filter( 'storeseeder_recipe_directories', function ( $dirs ) {
    $dirs['my-recipe'] = __DIR__ . '/my-recipe';

    return $dirs;
} );
```

## Everything else

| Filter                                           | What it does                                                       |
|--------------------------------------------------|--------------------------------------------------------------------|
| `storeseeder_capability`                         | One gate for the menu, REST, MCP and AJAX                          |
| `storeseeder_platform_writers_{id}`              | Replace or add a writer for one driver                             |
| `storeseeder_platform_supports_{id}`             | Override a driver's capability matrix                              |
| `storeseeder_platform_fields_{id}`               | Extra generation parameters only that driver understands           |
| `storeseeder_platform_search_{id}`               | What the entity pickers suggest                                    |
| `storeseeder_platform_admin_url_{id}`            | Where a resource lives in wp-admin                                 |
| `storeseeder_canonical_{resource}`               | Alter one entity before it is written                              |
| `storeseeder_canonical_entity`                   | The all-resources counterpart, running first                       |
| `storeseeder_before_write_{platform}_{resource}` | Act either side of a write                                         |
| `storeseeder_target_platform`                    | Force the resolved target                                          |
| `storeseeder_rest_controllers`                   | Register a controller                                              |
| `storeseeder_cli_commands`                       | Register a WP-CLI command                                          |
| `storeseeder_mcp_abilities`                      | Register an MCP ability                                            |
| `storeseeder_mcp_ability_definition`             | Amend one ability without replacing the class                      |
| `storeseeder_mcp_settings`                       | Override the three AI switches                                     |
| `storeseeder_rest_params`                        | Alter every endpoint's schema; runs before the per-endpoint filter |
| `storeseeder_locales`                            | The generatable locale list                                        |
| `storeseeder_purge_order`                        | The order resources are deleted in                                 |
| `storeseeder_admin_payload`                      | Add to what is inlined as `window.storeseederApi`                  |
| `storeseeder_sample_data_source`                 | Where the vocabulary archive comes from                            |
| `storeseeder_recipes_source`                     | Where the recipe archive comes from                                |

:::note[Change both URLs]
The two `_source` filters return a `repo_url` and a `zip_url`. `repo_url` is what the consent prompt shows an administrator, so changing only `zip_url` would misrepresent what they agreed to.
:::

## Adding a resource

Six pieces, and a driver that declares support while shipping no writer is reported as
`storeseeder_missing_writer` rather than failing once per item:

1. A generator extending `Generation\Generator`
2. A writer per driver, extending `Platforms\Writer`
3. That driver's `writer_classes()` and `capabilities()`
4. The canonical name in `Platforms\Resource`
5. A controller extending `Rest\Controller`
6. Admin registration in `src/lib/generators.ts`
