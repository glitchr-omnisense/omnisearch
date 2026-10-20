---
title: Drivers
order: 4
---

# Drivers

A driver is what an engine does for omnisearch, in its own terms:

```php
interface Driver
{
    public function create(ClassMetadata $metadata): void;          // the index, where it is not
    public function drop(ClassMetadata $metadata): void;
    public function schema(ClassMetadata $metadata): array;         // what create() describes to the engine
    public function index(ClassMetadata $metadata, object ...$objects): void;
    public function remove(ClassMetadata $metadata, string ...$ids): void;
    public function search(ClassMetadata $metadata, Request $request): Response;
}
```

and a `DriverFactory` (`name()`, `create(array $options)`) makes it from a connection's options;
`Registry` keeps one connection per name.

- A driver turns objects into documents itself - with `ORM\Transformer` (the fields read by
  `PropertyReader`, typed, the store's identifier as `id`) or its engine library's own way
  (omnisearch/typesense uses glitchr/typesense-bundle's transformer, so its documents are the
  bundle's).
- A `Request`'s `Filter`s, facets, sort and page are the same for all; `options` hold what only one
  engine knows, by that engine's names; a driver reads its own and ignores the rest.
- `search()` answers a `Response` with a status and the engine's message when the engine refuses or
  cannot be reached.

## The memory engine

`Omnisearch\Memory\MemoryDriver` (`memory`) keeps the documents in the process: a word of the term
matches a word of a field that starts with it (or holds it, in an infix field), the filters,
facets, sort and pages of a Request work as documented. For tests, and an application's first
steps; nothing outlives the process.

## Writing one

A package `omnisearch/<engine>`, namespace `Omnisearch\<Engine>\`: a `DriverFactory`, a `Driver`,
its tests on recorded answers of the engine and, when a server can run in Docker, the harness's
`BareTest` on it. Add it to `docker/harness/plugins.php` and to `OmnisearchBundle::FACTORIES`;
when the engine has its own Symfony bundle that should host the indexes, a `Bridge\Symfony\Host`
and an entry of `OmnisearchBundle::HOSTS`.
