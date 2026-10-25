<?php

namespace Omnisearch\Testing;

use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\DBAL\Facet;
use Omnisearch\DBAL\Filter;
use Omnisearch\DBAL\Hit;
use Omnisearch\DBAL\Request;
use Omnisearch\DBAL\Response;
use Omnisearch\Exception\InvalidConfigException;
use Omnisearch\Exception\OmnisearchException;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\Type;
use Omnisearch\Registry;
use PHPUnit\Framework\Attributes\After;

/**
 * What every search engine's driver does, as PHPUnit tests: a driver
 * package's test case (or an application's, for a driver of its own) uses
 * this trait, gives the factory, the options of a working connection and the
 * objects to index - and the suite checks the contract of Omnisearch\DBAL\Driver
 * on three documents:
 *
 * - the factory names its engine, the Registry connects it from
 *   {driver, options}; a required option left out is an InvalidConfigException;
 * - an index is created where it is not (twice is no error), described by
 *   schema(), dropped with its documents (twice is no error); a search of an
 *   index that is not there answers a failed Response (a status, a message),
 *   never an exception;
 * - documents are found by a word's prefix in the fields asked, with the words
 *   they matched; an empty term finds them all; the filters (=, != , in, not in,
 *   <, <=, >, >=; "=" on a list field means "holds") all hold; sort asc and
 *   desc; pages (perPage, page, found counts them all); facets count the values
 *   of the documents found, the most frequent first;
 * - a write replaces the document of the same identifier; remove() forgets it,
 *   an unknown identifier is no error;
 * - an engine that does not answer (unreachableOptions()) answers a search with
 *   a failed Response, and a write with an OmnisearchException.
 *
 * Each test indexes in an index of its own making and drops it afterwards; a
 * shared server is left as it was (give a random `prefix` where the engine has one).
 *
 *   final class MemoryDriverContractTest extends TestCase
 *   {
 *       use DriverContractTests;
 *
 *       protected function factory(): DriverFactory { return new MemoryDriverFactory(); }
 *       protected function options(): array { return []; }
 *       protected function document(string $id, string $title, int $rank, array $labels): object { return new ContractDocument($id, $title, $rank, $labels); }
 *   }
 *
 * The objects are of a class declaring an index (#[Index]) with the fields
 * `title` (Type::STRING), `rank` (Type::INT, sort) and `labels`
 * (Type::STRINGS, facet), identified by $id: Testing\ContractDocument is one,
 * for an engine that indexes any object; a driver that indexes entities gives
 * one of its own.
 *
 * Requires phpunit/phpunit (the package's require-dev).
 */
trait DriverContractTests
{
    private ?Driver $contractDriver = null;
    private ?ClassMetadata $contractMetadata = null;

    /** The factory under test. */
    abstract protected function factory(): DriverFactory;

    /** @return array<string, mixed> the options of a connection to a working engine (the same for the whole test) */
    abstract protected function options(): array;

    /**
     * An object of the indexed class, its fields title, rank and labels as given.
     *
     * @param list<string> $labels
     */
    abstract protected function document(string $id, string $title, int $rank, array $labels): object;

    /** The index the documents go to; by default the one their class declares. */
    protected function metadata(): ClassMetadata
    {
        $class = $this->document('0', 'metadata', 0, [])::class;

        return AttributeLoader::load($class) ?? throw new \LogicException(\sprintf('%s declares no index (#[Index]).', $class));
    }

    /** @return list<string> the options a connection cannot do without */
    protected function requiredOptions(): array
    {
        return [];
    }

    /** @return array<string, mixed>|null the options of a connection to an engine that does not answer; null for an engine in the process */
    protected function unreachableOptions(): ?array
    {
        return null;
    }

    private function contractDriver(): Driver
    {
        return $this->contractDriver ??= $this->factory()->create($this->options());
    }

    private function contractMetadata(): ClassMetadata
    {
        return $this->contractMetadata ??= $this->metadata();
    }

    #[After]
    protected function dropTheContractIndex(): void
    {
        if (null !== $this->contractDriver) {
            $this->contractDriver->drop($this->contractMetadata());
            $this->contractDriver = null;
        }
    }

    /** The three documents, indexed in a fresh index. */
    private function indexed(): Driver
    {
        $driver = $this->contractDriver();
        $metadata = $this->contractMetadata();
        $driver->create($metadata);
        $driver->index($metadata,
            $this->document('1', 'Mount Fuji at dawn', 3, ['travel', 'japan']),
            $this->document('2', 'Fujisan in music', 1, ['music', 'japan']),
            $this->document('3', 'A recipe for ramen', 2, ['food']),
        );

        return $driver;
    }

    private function searched(Request $request): Response
    {
        $response = $this->contractDriver()->search($this->contractMetadata(), $request);
        self::assertTrue($response->ok(), \sprintf('the search answered %d: %s', $response->status, $response->error));
        foreach ($response->hits as $hit) {
            self::assertInstanceOf(Hit::class, $hit);
            self::assertNotSame('', $hit->id, 'a hit has an identifier');
        }
        self::assertGreaterThanOrEqual(\count($response->hits), $response->found, 'found counts every page');

        return $response;
    }

    /** @return list<string> */
    private static function sorted(Response $response): array
    {
        $ids = $response->ids();
        sort($ids);

        return $ids;
    }

    public function testTheFactoryNamesItsEngineAndTheRegistryConnectsIt(): void
    {
        $factory = $this->factory();
        self::assertMatchesRegularExpression('~^[a-z][a-z0-9_-]*$~', $factory->name(), 'the engine\'s name in a configuration');

        $registry = new Registry([$factory], ['contract' => ['driver' => $factory->name(), 'options' => $this->options()]]);
        self::assertSame($factory->name(), $registry->driverName('contract'));
        self::assertInstanceOf(Driver::class, $registry->connection('contract')->driver());
        self::assertSame($registry->connection('contract'), $registry->connection(), 'made once, the default');
    }

    public function testARequiredOptionLeftOutIsAConfigurationError(): void
    {
        foreach ($this->requiredOptions() as $option) {
            try {
                $this->factory()->create(array_diff_key($this->options(), [$option => true]));
                self::fail(\sprintf('A connection was made without its "%s" option.', $option));
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($option, $e->getMessage());
            }
        }
        if ([] === $this->requiredOptions()) {
            self::assertInstanceOf(Driver::class, $this->factory()->create([]), 'no option required: made from none');
        }
    }

    public function testTheContractsIndexIsWhatTheSuiteExpects(): void
    {
        $metadata = $this->contractMetadata();
        [$title, $rank, $labels] = [$metadata->field('title'), $metadata->field('rank'), $metadata->field('labels')];
        self::assertNotNull($title, 'a field title');
        self::assertNotNull($rank, 'a field rank');
        self::assertNotNull($labels, 'a field labels');
        self::assertSame(Type::STRING, $title->type, 'title: a string');
        self::assertSame(Type::INT, $rank->type, 'rank: an int');
        self::assertTrue($rank->sort, 'rank: sortable');
        self::assertSame(Type::STRINGS, $labels->type, 'labels: strings');
        self::assertTrue($labels->facet, 'labels: a facet');
    }

    public function testAnIndexIsCreatedOnceDescribedAndDropped(): void
    {
        $driver = $this->contractDriver();
        $metadata = $this->contractMetadata();

        $driver->create($metadata);
        $driver->create($metadata); // there already: left as it is
        self::assertNotSame([], $driver->schema($metadata), 'the index as the engine is told it');
        self::assertSame(0, $this->searched(new Request())->found, 'a new index is empty');

        $driver->drop($metadata);
        $driver->drop($metadata); // not there: nothing
        $response = $driver->search($metadata, new Request('fuji'));
        self::assertFalse($response->ok(), 'an index not there answers a status');
        self::assertGreaterThanOrEqual(400, $response->status);
        self::assertNotSame('', (string) $response->error, 'and the engine\'s message');
        self::assertSame([], $response->hits);
    }

    public function testDocumentsAreFoundByTheirWords(): void
    {
        $this->indexed();

        $response = $this->searched(new Request('fuji', ['title']));
        self::assertSame(['1', '2'], self::sorted($response), 'a word\'s beginning finds it: Fuji, Fujisan');
        self::assertSame(2, $response->found);
        foreach ($response->hits as $hit) {
            self::assertSame([], array_diff(array_keys($hit->highlights), ['title']), 'matched in the fields asked');
            self::assertNotSame([], $hit->matchedTokens(), 'with the words it matched');
            foreach ($hit->matchedTokens() as $token) {
                self::assertStringStartsWith('fuji', mb_strtolower($token));
            }
        }
        self::assertStringContainsStringIgnoringCase('fuji', (string) json_encode($response->hits[0]->document['title'] ?? null, \JSON_UNESCAPED_UNICODE), 'the document as the engine holds it');

        self::assertSame(['3'], $this->searched(new Request('ramen', ['title']))->ids());
        self::assertSame(0, $this->searched(new Request('kyoto', ['title']))->found);
        self::assertSame(['1', '2', '3'], self::sorted($this->searched(new Request())), 'no term: every document');
        self::assertSame(['1', '2', '3'], self::sorted($this->searched(new Request(''))), 'an empty term: every document');
    }

    public function testFiltersHold(): void
    {
        $this->indexed();
        $cases = [
            '= on a list: holds' => [Filter::equals('labels', 'japan'), ['1', '2']],
            '!= on a list: holds not' => [Filter::notEquals('labels', 'japan'), ['3']],
            'in' => [Filter::in('labels', ['food', 'music']), ['2', '3']],
            'not in' => [Filter::notIn('labels', ['japan']), ['3']],
            '>' => [new Filter('rank', Filter::GREATER, 2), ['1']],
            '>=' => [new Filter('rank', Filter::GREATER_OR_EQUAL, 2), ['1', '3']],
            '<' => [new Filter('rank', Filter::LOWER, 2), ['2']],
            '<=' => [new Filter('rank', Filter::LOWER_OR_EQUAL, 2), ['2', '3']],
            '= on a number' => [Filter::equals('rank', 3), ['1']],
        ];
        foreach ($cases as $case => [$filter, $ids]) {
            self::assertSame($ids, self::sorted($this->searched(new Request(filters: [$filter]))), $case);
        }
        self::assertSame(['2'], $this->searched(new Request('fuji', ['title'], [Filter::equals('labels', 'music'), new Filter('rank', Filter::LOWER, 3)]))->ids(), 'a term and every filter at once');
    }

    public function testSortAndPages(): void
    {
        $this->indexed();

        self::assertSame(['2', '3', '1'], $this->searched(new Request(sort: [['rank', 'asc']]))->ids());
        self::assertSame(['1', '3', '2'], $this->searched(new Request(sort: [['rank', 'desc']]))->ids());

        $page = $this->searched(new Request(sort: [['rank', 'asc']], page: 2, perPage: 1));
        self::assertSame(['3'], $page->ids(), 'page two of one');
        self::assertSame(3, $page->found, 'found counts them all');
        self::assertSame(2, $page->page);
        self::assertSame([], $this->searched(new Request(sort: [['rank', 'asc']], page: 4, perPage: 1))->hits, 'past the last page: nothing');
    }

    public function testFacetsCountTheDocumentsFound(): void
    {
        $this->indexed();

        $response = $this->searched(new Request(facets: ['labels']));
        self::assertArrayHasKey('labels', $response->facets);
        $facet = $response->facets['labels'];
        self::assertInstanceOf(Facet::class, $facet);
        self::assertSame('labels', $facet->field);
        $counts = [];
        foreach ($facet->values as $value) {
            $counts[$value['value']] = $value['count'];
        }
        self::assertSame(['japan' => 2], \array_slice($counts, 0, 1, true), 'the most frequent first');
        ksort($counts);
        self::assertSame(['food' => 1, 'japan' => 2, 'music' => 1, 'travel' => 1], $counts);

        $found = $this->searched(new Request('fuji', ['title'], facets: ['labels']))->facets['labels']->values;
        $counts = array_column($found, 'count', 'value');
        ksort($counts);
        self::assertSame(['japan' => 2, 'music' => 1, 'travel' => 1], $counts, 'among the documents found only');
    }

    public function testAWriteReplacesAndARemovalForgets(): void
    {
        $driver = $this->indexed();
        $metadata = $this->contractMetadata();

        $driver->index($metadata, $this->document('1', 'Kyoto gardens', 3, ['travel']));
        self::assertSame(['2'], $this->searched(new Request('fuji', ['title']))->ids(), 'the old words are gone');
        self::assertSame(['1'], $this->searched(new Request('kyoto', ['title']))->ids());
        self::assertSame(3, $this->searched(new Request())->found, 'replaced, not added');

        $driver->remove($metadata, '1', 'not-indexed');
        self::assertSame(['2', '3'], self::sorted($this->searched(new Request())));
        $driver->remove($metadata, '1'); // gone already: no error
        self::assertSame(2, $this->searched(new Request())->found);
    }

    public function testAnEngineThatDoesNotAnswerAnswersAStatus(): void
    {
        $options = $this->unreachableOptions();
        if (null === $options) {
            $this->addToAssertionCount(1); // an engine in the process: always there

            return;
        }
        $driver = $this->factory()->create($options);
        $metadata = $this->contractMetadata();

        $response = $driver->search($metadata, new Request('fuji'));
        self::assertFalse($response->ok(), 'a search answers a status, never an exception');
        self::assertGreaterThanOrEqual(400, $response->status);
        self::assertNotSame('', (string) $response->error);

        try {
            $driver->index($metadata, $this->document('1', 'Mount Fuji at dawn', 3, ['travel']));
            self::fail('a write to an engine that does not answer: no exception');
        } catch (OmnisearchException $e) {
            self::assertNotSame('', $e->getMessage());
        }
    }
}
