---
title: Symfony
order: 15
---

# Symfony

`Omnisearch\Bridge\Symfony\OmnisearchBundle` is part of this package: no other package is needed.
It requires `symfony/config`, `symfony/dependency-injection` and `symfony/http-kernel` (suggested by
the core, present in any Symfony application), and `symfony/console` for its commands.

## Registration

```php
// config/bundles.php
return [
    // ...
    Omnisearch\Bridge\Symfony\OmnisearchBundle::class => ['all' => true],
];
```

```yaml
# config/packages/omnisearch.yaml
omnisearch:
    connections:
        default:
            driver: typesense
            options: { connection: default }       # glitchr/typesense-bundle's connection
    mappings: ['%kernel.project_dir%/src/Entity']  # the classes declaring an #[Index]; src/ by default
```

```php
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Manager;

public function __construct(private Manager $search, private Finder $blogArticle) {}
```

## The configuration tree

As the bundle's `configure()` declares it (dumped with Symfony's `YamlReferenceDumper`, the
dumper `config:dump-reference omnisearch` uses):

```yaml
omnisearch:

    # The first connection when null.
    default_connection:   null

    # The connections, by name: a driver (typesense, memory...) and its options.
    connections:

        # Prototype
        name:
            driver:               ~ # Required
            options:              []

    # The directories whose classes declare an #[Index]: src/ when none is given.
    mappings:             []
```

| Key | Type | Default | |
|---|---|---|---|
| `default_connection` | string or null | `null` | the connection of an index that names none; the first of `connections` when null |
| `connections.<name>.driver` | string | required | the driver factory's name: `typesense`, `memory`, or an application factory's |
| `connections.<name>.options` | any | `[]` | handed whole to the factory's `create()`; each driver's options are in [Configuration](configuration.md#driver-options) |
| `mappings` | list of strings | `[]` | the directories scanned for `#[Index]`; `%kernel.project_dir%/src` when empty; parameters are resolved |

## What the bundle registers

| Service | | |
|---|---|---|
| `Omnisearch\Mapping\MetadataRegistry` | public | built by `MetadataRegistry::fromArray()` from the metadata read when the container compiled |
| `Omnisearch\Registry` | public | the factories tagged `omnisearch.driver_factory`, the `connections`, the `default_connection` |
| `Omnisearch\ORM\Manager` | public | the registry, the metadata, and `ObjectStore` when DoctrineBundle is registered (none otherwise) |
| `omnisearch.finder.<index>` | public | `Manager::finder('<index>')`; also autowired as `Finder $<index>` (an argument named after the index) |
| `Omnisearch\Typesense\TypesenseDriverFactory` | private, autowired | when omnisearch/typesense is installed; given glitchr/typesense-bundle's `TypesenseManager` and Doctrine's `ObjectManager` when they exist |
| `Omnisearch\Memory\MemoryDriverFactory` | private, autowired | always; given the `ObjectStore` when there is one |
| `Omnisearch\Bridge\Doctrine\DoctrineObjectStore`, alias `Omnisearch\ORM\ObjectStore` | private | with DoctrineBundle; over the `doctrine` registry |
| `Omnisearch\ORM\Indexer`, `Omnisearch\Bridge\Doctrine\IndexListener` | private | with DoctrineBundle, when a connection is not hosted; the listener tagged `doctrine.event_listener` for `postPersist`, `postUpdate`, `preRemove`, `postRemove`, `postFlush` |
| `IndexesCommand`, `SchemaCommand` | tagged `console.command` | when symfony/console is installed |

The parameter `omnisearch.hosted_connections` lists the connections an engine's own bundle hosts.

Any service of the application that implements `Omnisearch\DBAL\DriverFactory` is tagged
`omnisearch.driver_factory` by autoconfiguration: a driver of the application's own is used by
naming it in `driver`.

The services compiled for an application with one memory connection and one `#[Index('note')]`
class under `src/` (`App\Note`: `text` infix, `labels` a facet, `rank` sortable):

```
Omnisearch\Mapping\MetadataRegistry
Omnisearch\Memory\MemoryDriverFactory
Omnisearch\ORM\Manager
Omnisearch\Registry
Omnisearch\Typesense\TypesenseDriverFactory
omnisearch.finder.note
```

(run on 2026-10-26 with Symfony 8.1's DependencyInjection; omnisearch/typesense was installed)

## The metadata at compile time

The bundle scans `mappings` twice - in `prependExtension()`, for the hosted engines, and in
`loadExtension()` - with `AttributeLoader::scan()`: only the PHP files whose text names
`Omnisearch\Mapping` are loaded. Each directory is a `DirectoryResource` of the container: in debug,
adding or changing a PHP file there recompiles it. The metadata is kept in the compiled container as
plain arrays (`ClassMetadata::toArray()`); nothing is read by reflection at run time.

An index name declared by two classes is a `MappingException` at compile time.

## Hosted engines

An engine whose own bundle should create and fill its indexes is a **host**
(`Omnisearch\Bridge\Symfony\Host`). The bundle knows one: `typesense`, hosted by
`Omnisearch\Typesense\Bridge\Symfony\TypesenseHost` when `Typesense\Bundle\TypesenseBundle` is
registered. For each connection whose driver has a host that `hosts()` the application,
`prependExtension()` calls `prepend()` with the connection's options and the indexes of that
connection (an index with no `connection` goes to `default_connection`, else the first one):

- the indexes become glitchr/typesense-bundle's `mappings` (prepended to its configuration), as if
  its YAML declared them: its `typesense:create`, `typesense:action`, `typesense:update` and its
  Doctrine listener serve them;
- omnisearch searches through the bundle's finders, and registers no Doctrine listener of its own
  for that connection.

A collection still declared in the bundle's YAML lives beside them; declare a collection in one
place, not both.

## Indexing on Doctrine's events

With DoctrineBundle, the connections no host serves are indexed by `IndexListener`, which runs the
[indexing rule](indexing.md): an entity of a declared class persisted or updated is indexed, one
removed is removed, everything sent on `postFlush`, consecutive writes of one index in one call.

> [!WARNING]
> Known defect: the bundle builds the `Indexer` with no `$onError` and has no option to give one, so an engine that refuses a write or cannot be reached makes `EntityManager::flush()` throw a `DriverException` from `postFlush`, after the database has committed, where the rule says the failure does not stop the application's flush. Register your own `Indexer` (with an `$onError`) and `IndexListener` in place of the bundle's when this matters.

## Commands

`omnisearch:indexes` lists the indexes the classes declare; `omnisearch:schema [index...]` prints the
schema each is created with, in its engine's terms, as JSON (every index when none is named). On the
application above:

```
$ bin/console omnisearch:indexes
 ------- ---------- ------------ -------- --------------------- 
  Index   Class      Connection   Driver   Fields (* facet)     
 ------- ---------- ------------ -------- --------------------- 
  note    App\Note   default      memory   text, labels*, rank  
 ------- ---------- ------------ -------- --------------------- 

$ bin/console omnisearch:schema note
{
    "note": {
        "name": "note",
        "fields": [
            {
                "name": "text",
                "type": "string",
                "facet": false,
                "infix": true
            },
            {
                "name": "labels",
                "type": "string[]",
                "facet": true,
                "infix": false
            },
            {
                "name": "rank",
                "type": "int",
                "facet": false,
                "infix": false
            }
        ]
    }
}
```

(run on 2026-10-26: the two commands on the compiled container's `Manager`, Symfony Console 8.1)

The memory engine's schema is its own short description; a Typesense index's is the collection's
schema as `TypesenseCollection::create()` sends it. Neither command calls the engine.

## Without DoctrineBundle

The `Manager` has no store: searches answer documents (`Finder::raw()`, `Result::hits()`), and
`Result::objects()` is empty. No listener is registered; index with `Manager::index()` or an
`Indexer` of the application's.
