---
title: Errors
order: 50
---

# Errors and exceptions

A search does not throw for the engine's sake: it answers a `Response` (a `Result`) that is not
`ok()`, with the status and the engine's message. Writes, configuration and mapping throw.

## The exceptions

```
\RuntimeException
└── Omnisearch\Exception\OmnisearchException      every exception of omnisearch and its drivers
    ├── DriverException                            the engine refused a write, or was not reached
    ├── InvalidConfigException                     a connection or driver not configured, or not installed
    └── MappingException                           an index or field that cannot work
```

`OmnisearchException` is not final: catch it for "anything omnisearch". The three below are
`final`.

### `DriverException`

Thrown by a driver's writes - `create()`, `drop()`, `index()`, `remove()` - when the engine refuses
or cannot be reached. Its code is the engine's HTTP status when there was one (400 a malformed
request or document, 404 no such collection...), 503 when there was none; its
previous exception is the engine library's. omnisearch/typesense prefixes the message with
`Typesense: `.

Creating an index that exists, dropping one that does not, removing an unknown identifier are not
errors.

### `InvalidConfigException`

| Message | Thrown by |
|---|---|
| `No "x" connection; configured: a, b.` | `Registry::connection()` |
| `No "y" driver for the "x" connection; installed: memory, typesense.` | `Registry::connection()` |
| `No "x" connection.` | `Registry::driverName()` |
| `A Typesense connection of its own needs the option "secret" (and "url", or "host").` | `TypesenseDriverFactory::create()` |
| `A Typesense connection of its own needs Doctrine's object manager (the factory's second argument).` | `TypesenseDriverFactory::create()` |

### `MappingException`

| Message | Thrown by |
|---|---|
| `A field declared on the class X needs a name.` | `AttributeLoader::load()` |
| `The field "f" is declared twice on X.` | `AttributeLoader::load()` |
| `The field "f" of X has the type "t"; one of string, string[], ...` | `AttributeLoader::load()` |
| `The index "i" is declared twice (X and Y).` | `AttributeLoader::scan()` (and the bundle at compile time) |
| `No index for "X"; declared: a, b.` | `MetadataRegistry::get()`, so `Manager::finder()`, `classMetadata()`, `connection()`, `index()` |

## What is not an `OmnisearchException`

`Omnisearch\DBAL\Filter`'s constructor throws PHP's `\InvalidArgumentException` for a filter that
can never work as written (an unknown operator, `in`/`not in` without an array): a mistake in the
code, thrown at once, not a failure to catch - `OmnisearchException`'s docblock says so. An index
omnisearch/typesense cannot make (the bundle does not know it, the driver has no transformer) is
an `InvalidConfigException`.

A driver catches its engine library's transport exceptions whatever HTTP client the library was
given: omnisearch/typesense catches the PSR-18 interfaces, so Symfony's `Psr18NetworkException`
becomes a failed search or a `DriverException` too
([searching](searching.md#when-the-engine-does-not-answer)).

## Failed searches

| `status()` | Engine | Meaning |
|---|---|---|
| 404 | memory | `No index "x".` - the index was not created, or was dropped |
| 404 | typesense | no such collection (`Typesense answered HTTP 404: no such collection.` when Typesense gave no message) |
| 4xx | typesense | the request was refused, with Typesense's message: a field not in the schema, infix asked on a field created without it (`Could not find ... in the infix index`) |
| 0, 5xx | typesense | not reached, or not ready (`Typesense answered HTTP 503: not reached, or not ready.`) |

```php
$result = $finder->search($query);
if (!$result->ok()) {
    // $result->status(), $result->error(); no hits, no objects
}
```

## What to catch

- Around a search: nothing of omnisearch; test `ok()`.
- Around `Manager::create()`, `drop()`, `index()`, a connection's writes: `DriverException`.
- Around the indexing on flush: give the `Indexer` an `$onError` ([Indexing](indexing.md#failures)).
- `InvalidConfigException` and `MappingException` are errors of the application's code or
  configuration: let them fail the request, the command or the container's compilation.
