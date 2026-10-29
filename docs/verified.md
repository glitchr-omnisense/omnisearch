---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

`docker compose run --rm omnisearch test` in `docker/`, beside Typesense 30.0
(`typesense/typesense:30.0.rca41`); the README and the commits state the runs: 48 tests in the
workspace, none skipped, on 2026-10-25.

- The mapping (`MappingTest`): attributes read in their order, metadata as plain values, a
  directory scanned, a sub-class in its parent's index, an unknown type and a nameless class field
  refused.
- `PropertyReaderTest`: paths, getters, lists, strings.
- The layer on the memory engine (`SearchTest`): objects in the order of the hits, filters, facets
  and pages, the indexing rule, an `Indexer` limited to other connections, a `Batch` grouping
  consecutive writes and handing a failure to `$onError`, a missing connection or driver named, an
  index not created answering a status.
- Doctrine (`Bridge/DoctrineTest`): entities indexed on flush and loaded back in the hits' order,
  on SQLite.
- The bundle (`Bridge/OmnisearchBundleTest`): the indexes of the attributes read at compile time, a
  finder injected by its index's name, no host for the memory engine; hosted by
  glitchr/typesense-bundle in omnisearch/typesense's `HostedTest`.
- In plain PHP (`BareTest`, `bin/bare`): nothing of Symfony nor Doctrine loaded on the memory
  engine, no class of a framework through omnisearch/typesense.
- The conformance suite ([Testing](testing.md#the-conformance-suite)) on the memory engine and on
  omnisearch/typesense beside the Typesense container.

## Run for this manual

On 2026-10-26, PHP 8.4.27, no network, the core's and omnisearch/typesense's checkouts on the
autoloader (the Symfony, Doctrine and typesense-bundle classes from an application's installed
`vendor/`): the plain-PHP script of [Installation](installation.md#plain-php), the examples of
[Searching](searching.md#a-worked-example) and [Indexing](indexing.md#on-doctrines-events), the
bundle's configuration tree, services and two commands ([Symfony](symfony.md)), the metadata
cache of [Mapping](mapping.md#reading-the-metadata), and omnisearch/typesense's mapping, request
translation and behaviour without a server (its manual). Their output is pasted as it came.

The harness itself was not run for this manual: its image is not built on the machine that wrote
it.

## On an application

glitchr's lesringostars declares four of its five collections with these attributes, through
omnisearch/typesense and hosted by glitchr/typesense-bundle: the schemas are those its server held,
byte for byte, its 628 documents and its blog's searches (pages, terms, the tags facet) unchanged;
the entities load in its Laravel twin with nothing of Symfony (README).

## What is not verified

- An engine other than Typesense and memory.
- Doctrine's MongoDB ODM: `DoctrineObjectStore` falls back on `findBy()`, never run.
- Laravel: no bridge; the service provider of [Laravel](laravel.md) is the Laravel twin's shape,
  not a tested package.
