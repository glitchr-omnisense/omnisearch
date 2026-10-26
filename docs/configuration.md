---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry

```php
new Omnisearch\Registry(iterable $factories, array $config, ?string $default = null);
```

| Argument | Type | | |
|---|---|---|---|
| `$factories` | iterable of `DriverFactory` | required | keyed by their `name()`; a later factory of the same name replaces an earlier one |
| `$config` | `array<string, array{driver: string, options?: array}>` | required | the connections by name |
| `$default` | ?string | `null` | the default connection; the first key of `$config` when null |

Each connection:

| Key | Type | Default | |
|---|---|---|---|
| `driver` | string | required | a factory's `name()`; unknown: `InvalidConfigException` on first use |
| `options` | array | `[]` | handed to the factory's `create()` on first use; the connection is then kept |

Nothing is checked when the registry is built: a missing driver or a bad option is said when the
connection is first asked for.

## The Symfony bundle

The `omnisearch` tree - `default_connection`, `connections.<name>.driver`,
`connections.<name>.options`, `mappings` - is in [Symfony](symfony.md#the-configuration-tree).
`connections` is the registry's `$config`, `default_connection` its `$default`.

## Driver options

### `memory`

`Omnisearch\Memory\MemoryDriverFactory` reads no option. Its constructor takes the store that
identifies the objects: `new MemoryDriverFactory(ObjectStore $store = new Identity())`. Give it the
same store as the `Manager`, so that the documents' identifiers are the ones the store loads back.

### `typesense`

`Omnisearch\Typesense\TypesenseDriverFactory` (omnisearch/typesense):
`new TypesenseDriverFactory(?TypesenseManager $manager = null, ?ObjectManager $objectManager = null)`.

| Option | Type | Default | |
|---|---|---|---|
| `connection` | string | `default` | the bundle's connection to use when the factory has glitchr/typesense-bundle's manager and it has that connection; also the name of the connection the driver makes on its own |
| `secret` | string | - | the API key; required when the driver makes its own connection (`InvalidConfigException` otherwise) |
| `url` | string | - | `http://host:port`; its parts fill `scheme`, `host`, `port`, `path` |
| `scheme` | string | `http` | |
| `host` | string | `localhost` | |
| `port` | int | `8108` | |
| `path` | string | `''` | kept by the bundle's configuration |
| `options` | array | `[]` | the Typesense PHP client's own options (`connection_timeout_seconds`, `num_retries`, `retry_interval_seconds`...) |
| `prefix` | string | `''` | put in front of every collection's name on the server, for the collections the driver makes (not those of the bundle's configuration) |

A connection of its own also needs Doctrine's object manager (the factory's second argument): the
bundle's transformer reads the entities' metadata. The provider's page has the whole behaviour:
[omnisearch/typesense](https://github.com/glitchr-omnisense/omnisearch-typesense/blob/1.x/docs/index.md#options).

## The attributes

`#[Index]` (`name`, `connection`, `options`) and `#[Field]` (`name`, `type`, `property`, `facet`,
`sort`, `infix`, `optional`, `locale`, `options`): every argument with its type and default is in
[Concepts](concepts.md#index); how names and paths are derived in [Mapping](mapping.md#names-and-paths).

Engine options read today:

| Where | Key | Engine | |
|---|---|---|---|
| `#[Index(options: ...)]` | `default_sorting_field` | typesense | the collection's default sort; the bundle's primary identifier field when not given |
| `#[Index(options: ...)]` | `token_separators` | typesense | default `['+', '-', '@', '.', ' ']` |
| `#[Index(options: ...)]` | `symbols_to_index` | typesense | default `['+']` |
| `#[Field(options: ...)]` | any | typesense | sent as the bundle's field options, with `sort`, `optional`, `locale` added from the field's own arguments |

Under the key `typesense` (`options: ['typesense' => [...]]`), or flat when there is no such key.

## Query options

`Query::option($name, $value)` (`Request::$options`):

| Option | Engine | |
|---|---|---|
| `infix` | typesense | `off`, `always` or `fallback`, one value or one per searched field (comma-separated); another value is dropped by the bundle |
| any other | typesense | sent as a Typesense search parameter of that name (`num_typos`, `pinned_hits`, `drop_tokens_threshold`...) |
| any | memory | none read |
