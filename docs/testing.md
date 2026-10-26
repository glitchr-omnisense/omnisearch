---
title: Testing
order: 60
---

# Testing

## Testing an application that searches

The memory engine is the test double: the same mapping, queries, filters, facets and pages, in the
PHP process, with nothing to start and nothing left behind. Point the connection at it in the test
environment.

```yaml
# config/packages/omnisearch.yaml
omnisearch:
    connections:
        default: { driver: typesense, options: { connection: default } }

when@test:
    omnisearch:
        connections:
            default: { driver: memory }
```

Under Symfony, with DoctrineBundle, the memory connection is not hosted by any engine's bundle, so
omnisearch's own `IndexListener` indexes the entities on flush and `DoctrineObjectStore` loads them
back: a functional test that creates an article and searches for it goes through the whole rule.
The indexes must exist first: call `Manager::create()` in the test's `setUp()` (a new kernel has a
new, empty memory engine).

```php
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ArticleSearchTest extends KernelTestCase
{
    public function testAPublishedArticleIsFound(): void
    {
        self::bootKernel();
        $search = self::getContainer()->get(Manager::class);
        $search->create();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->persist(new Article('Mount Fuji'));
        $entityManager->flush();                          // indexed by the listener

        $result = $search->finder(Article::class)->search((new Query('fuji'))->in('title'));
        self::assertTrue($result->ok());
        self::assertSame(['Mount Fuji'], array_map(fn (Article $a) => $a->getTitle(), $result->objects()));
    }
}
```

What the memory engine does not reproduce: an engine's ranking (the memory engine keeps the
documents' order unless sorted), typo tolerance, stemming, the engine's own options
(`Query::option()` is ignored) and its refusals of a schema. A test of those runs against the
engine itself, as omnisearch/typesense's tests do beside a Typesense container ([harness](harness.md)).

Without a framework, build the `Manager` on `MemoryDriverFactory` with an `ObjectStore` of the
test's ([Installation](installation.md#plain-php)); to check what was indexed without loading
objects, read the documents with `Finder::raw()`.

## Testing a driver's translation

A driver's pure functions can be tested without a server. omnisearch/typesense's own tests do it:
`TypesenseDriver::query($metadata, $request)->getHeaders()` (the Typesense parameters a request
becomes), `TypesenseDriver::filter($filter)` (the `filter_by` syntax), `TypesenseDriver::response()`
on a recorded answer, `Mapping::toTypesense($metadata)` (the bundle's mapping).

## The conformance suite

`Omnisearch\Testing\DriverContractTests` is the `Driver` contract as PHPUnit tests, a trait every
driver package uses in a `Tests/<Name>ContractTest.php` of its own (the memory engine in this core's
`Tests/MemoryDriverContractTest.php`, omnisearch/typesense in `Tests/TypesenseContractTest.php`).
It requires `phpunit/phpunit` (the core's require-dev; suggested).

### The test case

```php
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\Testing\ContractDocument;
use Omnisearch\Testing\DriverContractTests;
use PHPUnit\Framework\TestCase;

final class MemoryDriverContractTest extends TestCase
{
    use DriverContractTests;

    protected function factory(): DriverFactory
    {
        return new MemoryDriverFactory();
    }

    protected function options(): array
    {
        return [];
    }

    protected function document(string $id, string $title, int $rank, array $labels): object
    {
        return new ContractDocument($id, $title, $rank, $labels);
    }
}
```

| Method | Required | |
|---|---|---|
| `factory(): DriverFactory` | yes | the factory under test |
| `options(): array` | yes | the options of a connection to a working engine, the same for the whole test (give a random `prefix` on a shared server) |
| `document(string $id, string $title, int $rank, array $labels): object` | yes | an object of the indexed class with these values, identified by `$id` |
| `metadata(): ClassMetadata` | no | the index the documents go to; by default the one their class declares (`\LogicException` if none) |
| `requiredOptions(): array` | no | the options a connection cannot do without; `[]` by default |
| `unreachableOptions(): ?array` | no | the options of a connection to an engine that does not answer; `null` by default (an engine in the process) |

The documents' class declares an index with the fields `title` (`Type::STRING`), `rank`
(`Type::INT`, `sort: true`) and `labels` (`Type::STRINGS`, `facet: true`).
`Omnisearch\Testing\ContractDocument` (`#[Index('omnisearch_contract')]`) is one, for an engine that
indexes any object; a driver that indexes entities gives an entity of its own (omnisearch/typesense:
a Doctrine entity with an integer identifier).

Each test works on an index of its own making: an `#[After]` method drops it, so a shared server is
left as it was.

### Each test

The three documents: `1` "Mount Fuji at dawn" (rank 3, travel and japan), `2` "Fujisan in music"
(rank 1, music and japan), `3` "A recipe for ramen" (rank 2, food).

| Test | Checks |
|---|---|
| `testTheFactoryNamesItsEngineAndTheRegistryConnectsIt` | the name matches `^[a-z][a-z0-9_-]*$`; a `Registry` of `{driver, options}` gives that driver name and a `Driver`, the same connection twice and as the default |
| `testARequiredOptionLeftOutIsAConfigurationError` | each of `requiredOptions()` left out is an `InvalidConfigException` naming it; with none required, a driver is made from `[]` |
| `testTheContractsIndexIsWhatTheSuiteExpects` | the index has `title` (string), `rank` (int, sortable), `labels` (strings, facet) |
| `testAnIndexIsCreatedOnceDescribedAndDropped` | `create()` twice is no error, `schema()` is not empty, a new index is empty; `drop()` twice is no error; a search of the dropped index answers a status of at least 400, a message, no hits |
| `testDocumentsAreFoundByTheirWords` | `fuji` in `title` finds 1 and 2 (found 2), highlighted in `title` only, each matched token starting with "fuji", the document holding the title; `ramen` finds 3; `kyoto` nothing; no term and `''` find all three |
| `testFiltersHold` | `=` and `!=` on a list (holds, holds not), `in`, `not in`, `>`, `>=`, `<`, `<=`, `=` on a number, and a term with two filters at once |
| `testSortAndPages` | `rank` ascending 2, 3, 1 and descending 1, 3, 2; page 2 of one per page is 3, `found` 3, `page` 2; page 4 is empty |
| `testFacetsCountTheDocumentsFound` | `labels` counted (food 1, japan 2, music 1, travel 1), japan first; with the term `fuji` only the found documents' values (japan 2, music 1, travel 1) |
| `testAWriteReplacesAndARemovalForgets` | indexing id 1 again replaces it (its old words gone, three documents still); `remove('1', 'not-indexed')` forgets it, an unknown id and a second removal are no errors |
| `testAnEngineThatDoesNotAnswerAnswersAStatus` | with `unreachableOptions()`: a search answers a status of at least 400 and a message; an `index()` throws an `OmnisearchException` with a message. Skipped (counted as one assertion) when `null` |

Every successful search also checks that each hit is a `Hit` with an identifier and that `found`
is at least the number of hits.

An engine reached over the network runs the suite beside a server; omnisearch/typesense's test
case skips the tests that need one when no healthy server answers at `TYPESENSE_URL`, and runs the
four that need none (factory, options, mapping, an engine that does not answer).
