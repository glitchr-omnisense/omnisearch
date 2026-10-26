# omnisense/omnisearch

Omnisearch is search the way Doctrine is for SQL, whatever the engine. A class declares its index
with attributes (`#[Index]`, `#[Field]`), a manager hands out one finder per index, a query is
written once with a fluent `Query`, and the objects come back in the order of the hits, each with
the words it matched. Under that ORM sits a DBAL: connections, drivers, requests and responses, the
part an engine's package implements.

```php
use Omnisearch\Mapping\{Field, Index, Type};
use Omnisearch\ORM\Query;

#[Index('article')]
#[Field('title', type: Type::STRINGS, property: 'translations.title')]
#[Field('tags', type: Type::STRINGS, property: 'tags.slug', facet: true)]
#[Field('publishedAt', type: Type::DATETIME, sort: true)]
class Article { /* an entity that implements nothing of omnisearch */ }

$result = $manager->finder(Article::class)->search(
    (new Query('fuji'))->in('title')->where('tags', 'en-musique')->facet('tags')->sortBy('publishedAt', 'desc')->perPage(10)
);
$result->objects();                                      // Article entities, in the order of the hits
$result->hit($result->objects()[0])->matchedTokens();    // the words it matched
$result->facets(checked: ['tags' => ['en-musique']]);    // each tag, its count, checked or not
```

The core requires PHP 8.2 and nothing else. Doctrine and Symfony are suggested, never required:
their bridges (`Omnisearch\Bridge\Doctrine`, `Omnisearch\Bridge\Symfony`) are the only classes
that load them. The shape comes from glitchr/typesense-bundle, whose request/response and
manager/finder/mapping/transformer it generalises to any engine: what the bundle's YAML said of a
collection, an attribute says on the class.

Published as `omnisense/omnisearch`, formerly `omniscient/omnisearch`: the package `replace`s the
old name. The namespace is `Omnisearch\`; engines keep the `omnisearch/` vendor and the
`Omnisearch\<Engine>\` namespace.

## When to use it

- An application that searches its own objects (articles, products, people) with full-text words,
  filters, facets and pages, and wants the objects back rather than raw documents.
- Code that should not be tied to one engine: the mapping, the queries and the results are the
  same for each; only the connection's `driver` changes.
- Tests and first steps without a server: the memory engine runs the same queries in the PHP
  process.

It is not a search engine itself (the memory engine keeps documents in an array, for tests), and it
does not replace an engine's own tools: Typesense's collections, imports and listener stay
glitchr/typesense-bundle's in a Symfony application ([Symfony](symfony.md#hosted-engines)).

## Engines

| Package | Driver name | Engine | Notes |
|---|---|---|---|
| [`omnisearch/typesense`](https://github.com/glitchr-omnisense/omnisearch-typesense) | `typesense` | [Typesense](https://typesense.org), over glitchr/typesense-bundle's framework-free part | infix search, matched tokens, facets, discriminator sub-collections; objects must be Doctrine entities; in Symfony the bundle hosts the indexes |
| `Omnisearch\Memory` (in this package) | `memory` | the PHP process | prefix and infix matching, filters, facets, sort, pages; nothing outlives the process |

What every driver does is the `Omnisearch\DBAL\Driver` contract, checked by the conformance suite
([Testing](testing.md#the-conformance-suite)).

## The layers

| Namespace | |
|---|---|
| `Omnisearch\Mapping` | `#[Index]`, `#[Field]`, `Type`; `AttributeLoader` (reflection), `ClassMetadata`, `FieldMetadata`, `MetadataRegistry` |
| `Omnisearch\DBAL` | `Driver` (what an engine does), `DriverFactory`, `Connection`, `Request`, `Filter`, `Response`, `Hit`, `Facet`, `Batch` (writes gathered, sent together) |
| `Omnisearch\ORM` | `Manager`, `Finder`, `Query`, `Result`, `Indexer` (what to index on persist, update, remove), `Transformer` (object to document), `PropertyReader`, `ObjectStore` (where the objects live) |
| `Omnisearch\Registry` | the connections by name, from their configuration |
| `Omnisearch\Memory` | the memory engine: `MemoryDriver`, `MemoryDriverFactory`, `Identity` |
| `Omnisearch\Bridge\Doctrine` | `DoctrineObjectStore` (entities identified, loaded back in the hits' order), `IndexListener` (the indexing rule on Doctrine's events) |
| `Omnisearch\Bridge\Symfony` | `OmnisearchBundle`, its two commands, `Host` (an engine's own bundle hosting its indexes) |
| `Omnisearch\Testing` | `DriverContractTests`, `ContractDocument`: the conformance suite |
| `Omnisearch\Exception` | `OmnisearchException` and its three subclasses |

## How this manual is organised

- [Installation](installation.md): requirements, a whole script in plain PHP, Doctrine and Typesense.
- [Symfony](symfony.md) and [Laravel](laravel.md): wiring omnisearch in a framework.
- [Concepts](concepts.md): every class of the mapping, the DBAL and the ORM, with its members.
- Usage: [mapping indexes and fields](mapping.md), [searching](searching.md),
  [indexing](indexing.md).
- [Configuration reference](configuration.md): the registry, the bundle's tree, the attributes'
  arguments, the memory engine.
- [Errors](errors.md): the exceptions, when each is thrown, and what is answered instead.
- [Testing](testing.md): testing an application that searches, and the conformance suite.
- [The Docker harness](harness.md).
- [Writing a driver](drivers.md).
- [Verified, and not](verified.md).
