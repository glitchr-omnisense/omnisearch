---
title: Symfony
order: 5
---

# Symfony

```php
// config/bundles.php
Omnisearch\Bridge\Symfony\OmnisearchBundle::class => ['all' => true],
```

```yaml
# config/packages/omnisearch.yaml
omnisearch:
    connections:
        default:
            driver: typesense
            options: { connection: default }          # glitchr/typesense-bundle's connection
    mappings: ['%kernel.project_dir%/src/Entity']   # the classes declaring an #[Index]; src/ by default
```

```php
public function __construct(private Manager $search, private Finder $blogArticle) {}
```

- The indexes are read from the attributes when the container compiles (the directories are
  watched in debug) and kept in it: `MetadataRegistry`, `Manager`, `Registry`, a `Finder` per
  index (`omnisearch.finder.<index>`, injectable by the index's name).
- The drivers installed are registered and autowired (`omnisearch/typesense` is given the
  bundle's `TypesenseManager` when glitchr/typesense-bundle is there), with any `DriverFactory` of
  the application.
- **Hosted engines.** When an engine's own bundle is registered (glitchr/typesense-bundle for
  `typesense`), the indexes of that connection are handed to it before the container is built: they
  become its collections as if its configuration declared them - its `typesense:create`,
  `typesense:action`, `typesense:update` and its Doctrine listener serve them. omnisearch searches
  through it and registers no listener of its own for that connection.
- Otherwise, with DoctrineBundle, `Bridge\Doctrine\IndexListener` indexes the other connections on
  flush, and `DoctrineObjectStore` loads the entities back.
- Commands: `omnisearch:indexes` (each index: class, connection, driver, fields),
  `omnisearch:schema [index...]` (the schema each is created with, as JSON).

Symfony's components are suggested, not required: the bundle's classes are the only ones that
load them.
