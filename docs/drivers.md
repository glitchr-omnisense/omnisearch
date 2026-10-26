---
title: Drivers
order: 80
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
  cannot be reached - never an exception, and never an empty message.
- A write (`create()`, `drop()`, `index()`, `remove()`) the engine refuses, or that cannot reach it,
  throws an `Omnisearch\Exception\DriverException`, its code the engine's HTTP status (503 when
  there was none), the engine library's exception as its previous one. Creating an index that is
  there, dropping one that is not, removing an unknown identifier are no errors.

## The memory engine

`Omnisearch\Memory\MemoryDriver` (`memory`) keeps the documents in the process: a word of the term
matches a word of a field that starts with it (or holds it, in an infix field), the filters,
facets, sort and pages of a Request work as documented. For tests, and an application's first
steps; nothing outlives the process. Its whole behaviour: [Concepts](concepts.md#the-memory-engine).

## Writing one

A package `omnisearch/<engine>`, namespace `Omnisearch\<Engine>\`: a `DriverFactory`, a `Driver`,
its tests on recorded answers of the engine and, when a server can run in Docker, the harness's
`BareTest` on it. Add it to `docker/harness/plugins.php` and to `OmnisearchBundle::FACTORIES`;
when the engine has its own Symfony bundle that should host the indexes, a `Bridge\Symfony\Host`
and an entry of `OmnisearchBundle::HOSTS`.

The shape of a driver for an engine with an HTTP API, holding plain documents:

```php
namespace Omnisearch\Acme;

use Omnisearch\DBAL\{Driver, DriverFactory, Request, Response, Hit};
use Omnisearch\Exception\{DriverException, InvalidConfigException};
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\ORM\{ObjectStore, Transformer};

final class AcmeDriverFactory implements DriverFactory
{
    public function __construct(private readonly ObjectStore $store) {}

    public function name(): string
    {
        return 'acme';
    }

    public function create(array $options): Driver
    {
        if (empty($options['url'])) {
            throw new InvalidConfigException('An Acme connection needs the option "url".');
        }

        return new AcmeDriver(new AcmeClient($options['url']), new Transformer($this->store));
    }
}

final class AcmeDriver implements Driver
{
    public function __construct(private readonly AcmeClient $client, private readonly Transformer $transformer) {}

    public function index(ClassMetadata $metadata, object ...$objects): void
    {
        $documents = array_map(fn (object $o) => $this->transformer->document($metadata, $o), $objects);
        try {
            $this->client->upsert($metadata->index, $documents);
        } catch (AcmeError $e) {
            throw new DriverException('Acme: '.$e->getMessage(), $e->status ?: 503, $e);
        }
    }

    public function search(ClassMetadata $metadata, Request $request): Response
    {
        try {
            $answer = $this->client->search($metadata->index, /* the request in Acme's terms */);
        } catch (AcmeError $e) {
            return Response::failed($e->status ?: 503, $e->getMessage() ?: 'Acme not reached.');
        }

        return new Response($answer['total'], $request->page, array_map(
            fn (array $hit) => new Hit((string) $hit['id'], $hit['document'], $hit['highlights'] ?? [], $hit['score'] ?? null, $hit),
            $answer['hits'],
        ));
    }

    // create(), drop(), schema(), remove(): the same rules
}
```

(`AcmeClient` and `AcmeError` stand for the engine's own library.) Every exception a driver lets
out should be an `OmnisearchException`; catch the engine library's transport errors too, not only
its API errors - the client library may throw the HTTP client's own exceptions.

An application can also write a driver of its own without a package: under Symfony, any service
implementing `DriverFactory` is tagged `omnisearch.driver_factory` and its `name()` can be used as a
connection's `driver`.

## Conformance suite

`Omnisearch\Testing\DriverContractTests` is the `Driver` contract as PHPUnit tests: every driver
package runs it in a `Tests/<Name>ContractTest.php` of its own (the memory engine in this core's
`Tests/MemoryDriverContractTest.php`). The test case gives the factory, the options of a working
connection, and the objects to index - of a class declaring an index with the fields `title`
(`Type::STRING`), `rank` (`Type::INT`, sort) and `labels` (`Type::STRINGS`, facet).
`Omnisearch\Testing\ContractDocument` is one, for an engine that indexes any object; a driver that
indexes entities gives an entity of its own.

It indexes three documents in an index of its own, dropped after each test (give a random
`prefix` on a shared server), and checks:

| | |
|---|---|
| Factory and registry | the factory names its engine; the `Registry` connects it from `{driver, options}`, once |
| Options | each of `requiredOptions()` left out is an `InvalidConfigException` naming it |
| Index | created where it is not (twice: no error), described by `schema()`, dropped with its documents (twice: no error); a search of an index not there answers a failed `Response` - a status, a message |
| Words | a word's beginning finds the documents, in the fields asked, with the words they matched; an empty term finds them all |
| Filters | `=`, `!=`, `in`, `not in` on a list field (holds / holds not), `<`, `<=`, `>`, `>=`, `=` on a number, with a term |
| Sort and pages | ascending, descending; `perPage` and `page`, `found` counting every page, nothing past the last |
| Facets | the values of the documents found, counted, the most frequent first |
| Writes | a write replaces the document of its identifier; `remove()` forgets it, an unknown identifier is no error |
| An engine that does not answer | `unreachableOptions()` (null for an engine in the process): a search answers a failed `Response` with a message, a write throws an `OmnisearchException` |

`metadata()` (by default the index the documents' class declares) and `requiredOptions()`,
`unreachableOptions()` are hooks with defaults. An engine reached over the network runs it beside
a server (omnisearch/typesense: the harness's Typesense container) - the tests that need none
(factory, options, mapping, an engine that does not answer) run anyway. Each test, its documents
and its assertions: [Testing](testing.md#the-conformance-suite).

The suite proves the contract with the dependencies the package's tests install; an engine
library that picks its HTTP client at run time (php-http discovery) may behave otherwise in an
application with another client installed - omnisearch/typesense does
([searching](searching.md#when-the-engine-does-not-answer)).
