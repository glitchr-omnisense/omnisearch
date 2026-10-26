---
title: Concepts
order: 20
---

# Concepts

Three layers, as in Doctrine. The **mapping** says what is indexed, read from attributes into plain
metadata. The **DBAL** is what an engine does: a driver per engine, made by its factory, behind a
named connection; a search is a `Request`, its answer a `Response`. The **ORM** is what the
application uses: a `Manager`, a `Finder` per index, a fluent `Query`, a `Result` with the objects
loaded back, and the `Indexer` that keeps the indexes in step with a persistence.

## Mapping

### `#[Index]`

`Omnisearch\Mapping\Index`, an attribute on a class (`TARGET_CLASS`, not repeatable). Its objects
are documents of one index.

| Argument | Type | Default | |
|---|---|---|---|
| `name` | string | required | the index's name for the application; an engine may put a prefix in front of it on its server (Typesense's `prefix` option) |
| `connection` | ?string | `null` | the connection it lives on; the registry's default when null |
| `options` | array | `[]` | the engine's own settings of the index, under the engine's name (`['typesense' => ['default_sorting_field' => 'publishedAt']]`); a driver reads its own and ignores the rest |

### `#[Field]`

`Omnisearch\Mapping\Field`, an attribute on a class, a property or a method (repeatable). One field
of the document.

| Argument | Type | Default | |
|---|---|---|---|
| `name` | ?string | `null` | the field's name in the document. Required on the class; on a property, the property's name; on a method, its name without a leading `get`, `is` or `has` (`getReadingTime` gives `readingTime`) |
| `type` | string | `Type::STRING` | one of `Type`'s constants |
| `property` | ?string | `null` | the property path the value is read from ([PropertyReader](#propertyreader)); on a property or a method, that member's name; on the class, the field's name |
| `facet` | bool | `false` | its values can be counted and filtered on |
| `sort` | bool | `false` | results can be sorted by it |
| `infix` | bool | `false` | it can be matched by the middle of a word, where the engine can |
| `optional` | bool | `false` | a document may lack it (a null value is kept null, not cast) |
| `locale` | ?string | `null` | the language its text is tokenized in, for the engines that use it |
| `options` | array | `[]` | the engine's own field settings, under the engine's name or flat |

The attributes are classes of this package and nothing else: a class carrying them loads without
omnisearch installed (PHP reads an attribute's class only when asked to).

### `Type`

`Omnisearch\Mapping\Type`: the field types, the same for every engine; a driver maps each onto its
own. A type ending in `[]` is a list (`Type::isList($type)`).

| Constant | Value | In a document |
|---|---|---|
| `STRING` | `string` | a string (an object by its `__toString()`, null as `''`) |
| `STRINGS` | `string[]` | a list of strings |
| `INT` | `int` | an integer |
| `INTS` | `int[]` | a list of integers |
| `BIGINT` | `bigint` | an integer (64-bit where the engine distinguishes) |
| `FLOAT` | `float` | a float |
| `FLOATS` | `float[]` | a list of floats |
| `BOOL` | `bool` | a boolean |
| `BOOLS` | `bool[]` | a list of booleans |
| `DATETIME` | `datetime` | a Unix timestamp (a `DateTimeInterface` by `getTimestamp()`, anything else cast to int) |
| `GEOPOINT` | `geopoint` | `[latitude, longitude]`, floats |

`Type::ALL` lists them; any other type is a `MappingException` when the class is loaded.

### `FieldMetadata` and `ClassMetadata`

What the attributes said, as plain values (`final`, every property `public readonly`):

- `FieldMetadata`: `name`, `type`, `property`, `facet`, `sort`, `infix`, `optional`, `locale`,
  `options` - the arguments of `#[Field]` once the defaults are filled. `path()` is the property
  path (`property`, else `name`). `toArray()` / `fromArray()`.
- `ClassMetadata`: `class` (the declaring class), `index` (its name), `connection` (?string),
  `fields` (`FieldMetadata` by name, in their declared order: the class's attributes first, then the
  properties', then the methods'), `options` (the index's). `field($name)` returns one or null,
  `facets()` the names of the facet fields. `toArray()` / `fromArray()` keep it in a compiled
  container or a cache file.

### `AttributeLoader`

`Omnisearch\Mapping\AttributeLoader`, reflection only:

- `load(string $class): ?ClassMetadata` - the class's `#[Index]` and every `#[Field]`; null when it
  declares no index. A field on the class without a name, a field declared twice, an unknown type:
  `MappingException`.
- `scan(array $directories): array` - the metadata of every class declared in the `.php` files under
  these directories, by index name, sorted. A file is read as tokens and loaded only when its text
  names `Omnisearch\Mapping`, so the rest of an application's code is not autoloaded. A directory
  that does not exist is skipped. Two classes declaring the same index: `MappingException`.

### `MetadataRegistry`

`Omnisearch\Mapping\MetadataRegistry`: the application's indexes, by index name and by class.

| Method | |
|---|---|
| `__construct(iterable $metadata = [])` | from `ClassMetadata`s |
| `fromDirectories(array $directories)` (static) | `AttributeLoader::scan()` |
| `fromArray(array $metadata)` (static) | from `ClassMetadata::toArray()`s |
| `add(ClassMetadata $metadata)` | adds one; an index of the same name replaces the earlier one |
| `all()` | every `ClassMetadata`, by index name |
| `has(string $indexOrClass)`, `find(string\|object $indexOrClass)`, `get(...)` | by index name, class name or object; an object of a sub-class belongs to its nearest declared parent's index. `find()` answers null, `get()` throws `MappingException` (`No index for "X"; declared: a, b.`) |

## DBAL

### `Driver`

`Omnisearch\DBAL\Driver`, the interface an engine implements, in the engine's own terms:

| Method | Contract |
|---|---|
| `create(ClassMetadata $metadata): void` | the index created where it is not; an existing one is left as it is |
| `drop(ClassMetadata $metadata): void` | the index dropped with its documents; nothing when there is none |
| `schema(ClassMetadata $metadata): array` | the index as `create()` describes it to the engine, to read or compare; calls nothing |
| `index(ClassMetadata $metadata, object ...$objects): void` | the objects' documents written, created or replaced |
| `remove(ClassMetadata $metadata, string ...$ids): void` | the documents of these identifiers removed; an unknown one is no error |
| `search(ClassMetadata $metadata, Request $request): Response` | an engine that cannot be reached or refuses answers a `Response` with its status and message, never an exception |

A write the engine refuses, or that cannot reach it, throws a `DriverException`
([Errors](errors.md)).

### `DriverFactory`

`Omnisearch\DBAL\DriverFactory`: `name(): string` is the engine's name in a connection's
configuration (`typesense`, `memory`); `create(array $options): Driver` makes a driver from a
connection's options. A required option left out is an `InvalidConfigException` naming it.

### `Connection`

`Omnisearch\DBAL\Connection` (`final`): a named driver. `$name` (public readonly), `driver()`, and
`create()`, `drop()`, `schema()`, `index()`, `remove()`, `search()` handed to the driver - except
that `index()` with no object and `remove()` with no identifier call nothing.

### `Registry`

`Omnisearch\Registry` (`final`): the application's connections by name.

```php
new Registry(iterable $factories, array $config, ?string $default = null)
// $config: ['<name>' => ['driver' => '<factory name>', 'options' => [...]], ...]
```

| Method | |
|---|---|
| `connection(?string $name = null): Connection` | made on first use by the factory named by `driver`, kept; the default when null. `No "x" connection; configured: a, b.` or `No "y" driver for the "x" connection; installed: memory.` as `InvalidConfigException` |
| `has(string $name): bool` | configured or not |
| `defaultName(): string` | `$default`, else the first configured name |
| `names(): array` | the configured names |
| `driverName(string $name): string` | the `driver` of a connection, without making it |

### `Request`

`Omnisearch\DBAL\Request` (`final`, every property `public readonly`): a search, as every engine is
asked it. `Query::request()` builds one; a driver reads it.

| Property | Type | Default | |
|---|---|---|---|
| `term` | ?string | `null` | the words searched; null or `''`: every document |
| `fields` | list of strings | `[]` | the fields the term is searched in, by importance; `[]`: the driver's choice (memory: every field; Typesense: every string field) |
| `filters` | list of `Filter` | `[]` | all must hold |
| `facets` | list of strings | `[]` | the fields whose values are counted |
| `maxFacetValues` | ?int | `null` | values per facet; null: the engine's default (memory: 10) |
| `sort` | list of `[field, 'asc'\|'desc']` | `[]` | in order |
| `page` | int | `1` | the page, from 1 |
| `perPage` | ?int | `null` | hits per page; null: the engine's default (memory: 10) |
| `options` | array | `[]` | what only one engine knows, by its names; a driver reads those it knows |
| `cacheable` | bool | `false` | the answer and the objects loaded from it may be cached |

`option(string $name, mixed $default = null)` reads one of `options`.

### `Filter`

`Omnisearch\DBAL\Filter` (`final`): a condition on a field, `field`, `operator`, `value` (public
readonly). On a list field, `=` means "holds" and `!=` "holds not".

| Constant | Operator | Value |
|---|---|---|
| `EQUALS` | `=` | a scalar |
| `NOT_EQUALS` | `!=` | a scalar |
| `IN` | `in` | a list |
| `NOT_IN` | `not in` | a list |
| `LOWER` | `<` | a scalar |
| `LOWER_OR_EQUAL` | `<=` | a scalar |
| `GREATER` | `>` | a scalar |
| `GREATER_OR_EQUAL` | `>=` | a scalar |

Named constructors: `Filter::equals()`, `notEquals()`, `in()`, `notIn()`. An unknown operator, or
`in`/`not in` without an array, is an `\InvalidArgumentException`. `matches(mixed $actual): bool`
evaluates the filter on a document's value in PHP (the memory engine's way): `=` and `!=` compare
loosely (`in_array(..., false)`), `in` and `not in` compare as strings, the comparisons compare
strings with `strcmp()` and numbers with `<=>`, and a list matches when one of its values does.

### `Response`, `Hit`, `Facet`

`Omnisearch\DBAL\Response` (`final`, public readonly): what the engine answered.

| Property | Type | |
|---|---|---|
| `found` | int | documents found in all, every page |
| `page` | int | the page answered |
| `hits` | list of `Hit` | this page's documents, in the engine's order |
| `facets` | array of `Facet` by field | |
| `status` | int | 200 when the engine answered; otherwise the HTTP status (400 refused, 404 no such index, 503 or 0 not reached) |
| `error` | ?string | the engine's message when `status` is not 200 |
| `raw` | array | the engine's own answer, without its hits |

`Response::failed(int $status, string $error)` builds a failed one; `ok()` is `status === 200`;
`ids()` the hits' identifiers.

`Omnisearch\DBAL\Hit` (`final`, public readonly): `id` (string), `document` (the fields as the
engine holds them), `highlights` (by field, the words of the term it matched there, in the engine's
order), `score` (?float: Typesense's `text_match`; null on the memory engine), `raw` (the engine's
own hit). `matchedTokens()` lists every matched word, field after field.

`Omnisearch\DBAL\Facet` (`final`, public readonly): `field`, `values` (a list of
`['value' => string, 'count' => int]`, plus `highlighted` where the engine gives it, the most
frequent first) and `extra` (what else the engine says of it: Typesense's `stats`, `sampled`...).

### `Batch`

`Omnisearch\DBAL\Batch` (`final`): writes gathered and sent together. `index($connection,
$metadata, $object)` and `remove($connection, $metadata, $id)` add one, `count()` counts them,
`rollBack()` forgets them. `commit(?callable $onError = null): int` sends them in the order they
came - consecutive writes of the same kind on the same connection and index in one call, so a
removal after an indexing of the same document wins - and returns how many writes were sent. A
failed call goes to `$onError($exception, $connection, $metadata)`, and the next group is still sent;
with no `$onError` the exception is thrown, and the groups after it are not sent and are
forgotten (`commit()` empties the batch first). A search engine has
no transaction: what was sent before a failure stays sent.

## ORM

### `Manager`

`Omnisearch\ORM\Manager` (`final`): the application's search, as an EntityManager is its database.

```php
new Manager(Registry $registry, MetadataRegistry $metadata, ?ObjectStore $store = null)
```

| Method | |
|---|---|
| `registry()`, `metadata()`, `store()` | what it was built with |
| `classMetadata(string\|object $indexOrClass): ClassMetadata` | by index name, class or object; `MappingException` when none |
| `connection(string\|object $indexOrClass): Connection` | the connection an index lives on (a `ClassMetadata` is accepted too) |
| `finder(string\|object $indexOrClass): Finder` | the index's finder, made once |
| `create()` | every index created where it is not |
| `drop()` | every index dropped |
| `index(object ...$objects)` | these objects indexed now, each in its class's index, one call per object |

### `Finder`

`Omnisearch\ORM\Finder` (`final`): the searches of one index. `$metadata` (public readonly) is its
`ClassMetadata`.

| Method | |
|---|---|
| `raw(Query\|Request $query): Response` | the engine's answer: documents only |
| `search(Query\|Request $query): Result` | the answer with the objects loaded from the `ObjectStore`, in the order of the hits; without a store, or when the answer failed or found nothing, a `Result` with no objects |
| `facets(array $fields, ?Query $query = null, array $checked = [], bool $sortByValue = false): array` | the facets of these fields among the documents `$query` finds (every document when null), with no hit asked for (`perPage(0)`); the same shape as `Result::facets()` |

### `Query`

`Omnisearch\ORM\Query` (`final`): a search written fluently; every method returns the query.

| Method | |
|---|---|
| `new Query(?string $term = null)`, `term(?string)` | the words |
| `in(string ...$fields)` | adds fields to search in, by importance |
| `where($field, $value)`, `whereNot($field, $value)`, `whereIn($field, array $values)` | `=`, `!=`, `in` |
| `filter(Filter $filter)` | any `Filter` |
| `facet(string ...$fields)` | adds fields to count |
| `maxFacetValues(?int $max)` | |
| `sortBy(string $field, string $direction = 'asc')` | adds a sort; anything but `desc` (any case) is `asc` |
| `page(int $page)` | at least 1 |
| `perPage(?int $perPage)` | |
| `option(string $name, mixed $value)` | an engine's own parameter, by its name (`->option('infix', 'always')`) |
| `cacheable(bool $cacheable = true)` | |
| `request(): Request` | the `Request` it describes |

### `Result`

`Omnisearch\ORM\Result` (`final`): a search's answer for the application. `$response` (public
readonly) is the engine's `Response`.

| Method | |
|---|---|
| `ok()`, `status()`, `error()` | the response's |
| `found()`, `page()` | |
| `totalPages(int $perPage): int` | `ceil(found / perPage)` |
| `hits(): array` | the response's hits |
| `objects(): array` | the objects loaded, in the order of the hits; a hit whose object is gone is left out |
| `hit(object $object): ?Hit` | the hit an object was loaded from (matched by the store's `identify()`) |
| `facets(array $checked = [], bool $sortByValue = false): array` | by field: `['field' => ..., 'counts' => [value => ['value', 'count', 'checked', ...]], 'extra' => [...]]`; `checked` is true for the values given in `$checked[field]`; `$sortByValue` orders the values by `strcmp()` instead of the engine's order |

`Result::facetsOf(Response $response, ...)` (static) is the same on a bare `Response`.

### `ObjectStore`

`Omnisearch\ORM\ObjectStore`: where the objects behind the documents live.

- `identify(object $object): string` - the identifier of an object's document;
- `load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array` - the objects of these
  identifiers that are found, in the order of `$ids`.

Two are shipped: `Omnisearch\Bridge\Doctrine\DoctrineObjectStore` (Doctrine's entities, below) and
`Omnisearch\Memory\Identity` (an object's `getId()`, else its public `$id`; loads nothing back -
the memory engine's default).

### `DoctrineObjectStore`

`new DoctrineObjectStore(ObjectManager|ManagerRegistry $doctrine)`. An entity is identified by its
identifier's values, joined by `-` when composite (an object value by its `__toString()`). `load()`
asks the entity's manager in one query: with the ORM a `SELECT e FROM <class> e WHERE e.<id> IN
(:ids)`, made cacheable in the second-level cache (and in the entity's cache region) when
`$cacheable`; with another manager `findBy([<id> => $ids])` (Doctrine's MongoDB ODM, not
verified). The entities come back in the order of the identifiers, those not found left out.

> [!WARNING]
> Known defect: `load()` queries the first identifier field only, with the joined strings, where `identify()` joins a composite identifier with `-`: an entity with a composite identifier is indexed but never loaded back, and `Result::objects()` leaves it out.

### `Transformer`

`Omnisearch\ORM\Transformer` (`final`): an object into its document, for the drivers that hold plain
documents (the memory engine). `document(ClassMetadata $metadata, object $object): array` is
`['id' => $store->identify($object)]` followed by each field read by its path and cast to its type.
A list keeps its non-null, non-empty values, each cast to the list's item type; a single value is
cast to the field's type, except null on an `optional` field, which stays null. An engine's package
may convert objects its own way (omnisearch/typesense uses glitchr/typesense-bundle's transformer).

### `PropertyReader`

`Omnisearch\ORM\PropertyReader::read(object $object, string $path, bool $list = false): mixed`
reads a value by a dotted path, without a framework:

- each segment is read by a public getter (`get<Segment>`, `is<Segment>`, `has<Segment>`), a public
  method of that very name (`isAudible`), a public property, an `ArrayAccess` key, or `__get()`, in
  that order; an array is read by key; what cannot be read is null, and the path stops at a null or
  false value;
- a segment on a list (an array, a `Traversable` such as a Doctrine collection) is read on every
  item: `translations.title` gives every translation's title;
- a list at the end is an array whose objects with `__toString()` are strings (`tags` gives each
  tag's string); nested arrays are flattened; with `$list`, a single value is wrapped in an array.

### `Indexer`

`Omnisearch\ORM\Indexer` (`final`): the indexing rule, independent of any persistence's events.

```php
new Indexer(Manager $manager, ?callable $onError = null, ?array $connections = null)
```

`persisted()`, `updated()`, `removing()` (before the object goes: its identifier taken from the
manager's store), `removed()`, `flush(): int` (the writes sent as one `Batch`), `clear()` (forgets
what is pending), `supports(object): bool` (a declared class, on one of `$connections` when given).
[Indexing](indexing.md) describes it in use.

## The memory engine

`Omnisearch\Memory\MemoryDriverFactory` (`memory`, no option) makes a `MemoryDriver` over a
`Transformer` and the store given to the factory (`Identity` by default). The driver keeps documents
by index and identifier in the process:

- **create, drop**: an empty index made where it is not; dropped with its documents;
- **schema**: `['name' => index, 'fields' => [['name', 'type', 'facet', 'infix'], ...]]`;
- **search** on an index not created: `Response::failed(404, 'No index "x".')`;
- **words**: the term is lower-cased and split on spaces (`*` is no word); each word must match at
  least one field - a token of the field's text (split on what is neither a letter nor a digit) that
  starts with the word, or holds it anywhere in an `infix` field. The fields are `Request::$fields`,
  every field of the index when none. The matched tokens are the hit's highlights, as written in
  the document;
- **filters**: `Filter::matches()` on the document's value;
- **sort**: by each sort key in turn (PHP's `<=>` on the document's values), stable;
- **facets**: the values of the hits found (a boolean as `true`/`false`), the most frequent first,
  `maxFacetValues` of them (10 by default);
- **pages**: `perPage` (10 by default) from `page`; `found` counts every hit; `score` is null.

Nothing outlives the process, and two drivers share nothing: each `create()` of the factory makes
an empty one.
