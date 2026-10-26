---
title: Searching
order: 31
---

# Searching: queries, results, facets

```php
$query = (new Query($term))
    ->in('title', 'excerpt', 'content')       // the fields searched, by importance
    ->where('state', 'publish')                // =; on a list field: holds
    ->whereNot('state', 'draft')
    ->whereIn('tags', ['a-table', 'voyages'])
    ->filter(new Filter('readingTime', Filter::LOWER, 10))
    ->facet('tags', 'audio')->maxFacetValues(250)
    ->sortBy('publishedAt', 'desc')
    ->page(2)->perPage(10)
    ->option('infix', 'always')               // an engine's own parameter, by its name
    ->cacheable();                             // the answer and the objects may be cached

$finder = $manager->finder('blogArticle');     // or Article::class, or an Article
$result = $finder->search($query);             // the objects loaded
$response = $finder->raw($query);              // the documents only
```

| `Result` | |
|---|---|
| `ok()`, `status()`, `error()` | an engine that cannot be reached or refuses answers a status (503, 400...) and its message, never an exception |
| `found()`, `page()`, `totalPages($perPage)` | |
| `objects()` | in the order of the hits; a hit whose object is gone is left out |
| `hit($object)` | its `Hit`: `id`, `document`, `highlights` (the matched words by field), `matchedTokens()`, `score`, `raw` |
| `facets($checked, $sortByValue)` | by field: `counts` by value (`value`, `count`, the engine's `highlighted`, `checked`), the engine's `extra` |

`$finder->facets(['tags'], $query, $checked, $sortByValue)` counts without the hits.

## A worked example

On the memory engine, four notes of this class:

```php
namespace App;

use Omnisearch\Mapping\{Field, Index, Type};

#[Index('note')]
#[Field('text', infix: true)]
#[Field('labels', type: Type::STRINGS, facet: true)]
final class Note
{
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        #[Field(type: Type::INT, sort: true)] public readonly int $rank = 0,
        public readonly array $labels = [],
    ) {
    }
}
```

```php
<?php // searching.php

require __DIR__.'/vendor/autoload.php';

use App\Note;
use Omnisearch\DBAL\Filter;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\{Manager, Query};
use Omnisearch\Registry;

$manager = new Manager(new Registry([new MemoryDriverFactory()], ['default' => ['driver' => 'memory']]), MetadataRegistry::fromDirectories([__DIR__.'/app/src']));
$manager->create();
$manager->index(
    new Note('1', 'Mount Fuji at dawn', 3, ['travel', 'japan']),
    new Note('2', 'Fujisan in music', 1, ['music', 'japan']),
    new Note('3', 'A recipe for ramen', 2, ['food']),
    new Note('4', 'Kyoto gardens', 5, ['travel', 'japan']),
);
$finder = $manager->finder('note');

$show = fn (string $label, Query $q) => printf("%-34s %s\n", $label, implode(' ', $finder->raw($q)->ids()) ?: '-');
$show('fuji in text', (new Query('fuji'))->in('text'));
$show('uji in text (infix)', (new Query('uji'))->in('text'));
$show('labels = japan', (new Query())->where('labels', 'japan'));
$show('labels != japan', (new Query())->whereNot('labels', 'japan'));
$show('labels in [food, music]', (new Query())->whereIn('labels', ['food', 'music']));
$show('rank >= 3, by rank desc', (new Query())->filter(new Filter('rank', Filter::GREATER_OR_EQUAL, 3))->sortBy('rank', 'desc'));
$show('page 2 of 2 per page, by rank', (new Query())->sortBy('rank')->page(2)->perPage(2));

$result = $finder->search((new Query())->facet('labels')->perPage(2));
printf("found %d, page %d, pages %d, hits %d\n", $result->found(), $result->page(), $result->totalPages(2), count($result->hits()));
echo json_encode($finder->facets(['labels'], (new Query('japan'))->in('labels'), ['labels' => ['travel']], sortByValue: true)), "\n";
```

```
$ php searching.php
fuji in text                       1 2
uji in text (infix)                1 2
labels = japan                     1 2 4
labels != japan                    3
labels in [food, music]            2 3
rank >= 3, by rank desc            4 1
page 2 of 2 per page, by rank      1 4
found 4, page 1, pages 2, hits 2
{"labels":{"field":"labels","counts":{"japan":{"value":"japan","count":3,"checked":false},"music":{"value":"music","count":1,"checked":false},"travel":{"value":"travel","count":2,"checked":true}},"extra":[]}}
```

(run on 2026-10-26, PHP 8.4.27, the core's checkout, no network)

No store is given to the manager here, so `search()` would answer no objects: `raw()` and the hits'
identifiers are what this example reads.

## Words and fields

- `in()` names the fields the term is searched in, by importance; called several times, it adds.
  With none, the driver chooses: the memory engine searches every field, omnisearch/typesense every
  `string` and `string[]` field.
- A word is found by its beginning (`fuji` finds "Fujisan"); in an `infix` field, by its middle
  too where the engine does it - always on the memory engine, on Typesense only when the query asks
  (`->option('infix', 'always')` or `'fallback'`).
- On the memory engine every word of the term must be found, each in one of the fields; Typesense
  applies its own rules (typos, `drop_tokens_threshold`...). A null or empty term finds every
  document (with the filters applied), on both.
- `Result::hit($object)->highlights` gives the matched words by field; `matchedTokens()` all of them,
  field after field - to mark them in a page.

## Filters

`where()`, `whereNot()`, `whereIn()` and `filter(new Filter(...))` add conditions; every condition
must hold. On a list field (`labels`), `=` means "holds the value", `!=` "does not hold it", `in`
"holds one of them", `not in` "holds none". The comparisons (`<`, `<=`, `>`, `>=`) are for numbers
and dates (a `DATETIME` field holds a timestamp: compare with `$date->getTimestamp()`).

## Facets

`facet('labels')` counts the values of the documents found, at most `maxFacetValues()` per field
(the engine's default when not set: 10 on the memory engine). `Result::facets()` gives them by
field and by value, with `checked` set for the values given:

```php
$facets = $result->facets(checked: ['tags' => $request->query->all('tags')], sortByValue: true);
foreach ($facets['tags']['counts'] as $value => $count) {
    // $count['value'], $count['count'], $count['checked'], and $count['highlighted'] on Typesense
}
```

The engine's order is the most frequent first; `sortByValue: true` orders by value (`strcmp()`).
`Finder::facets($fields, $query)` asks the engine for the counts only (`perPage(0)`): the values of
a filter list, without loading a page of hits.

## Sort and pages

`sortBy($field, 'asc'|'desc')` adds a sort key; several keys apply in order. `page()` starts at 1
(a lower number becomes 1); `perPage()` defaults to the engine's (10 on the memory engine).
`found()` counts every page; `totalPages($perPage)` is `ceil(found / perPage)`; a page past the last
has no hits.

## An engine's own parameters

`option($name, $value)` passes what only one engine knows, by that engine's name; another engine
ignores it. omnisearch/typesense sends `infix` through the bundle's setter and every other option as
a Typesense search parameter (`num_typos`, `pinned_hits`, `prioritize_exact_match`...). The memory
engine reads none.

## Caching

`cacheable()` marks the search: the engine's answer may be kept (omnisearch/typesense: the bundle
finder's cache, a successful answer only, cleared by the driver after each write of the index), and
`DoctrineObjectStore` makes the query that loads the entities cacheable in Doctrine's second-level
cache. The memory engine ignores it.

## When the engine does not answer

`search()` and `raw()` do not throw for an engine's refusal: the `Result` (or `Response`) is not
`ok()`, `status()` is the HTTP status, `error()` the engine's message, there are no hits and no
objects. A page shows "search unavailable" instead of failing:

```php
$result = $finder->search($query);
if (!$result->ok()) {
    $logger->warning('Search failed', ['status' => $result->status(), 'error' => $result->error()]);
}
```

> [!WARNING]
> Known defect: on omnisearch/typesense with symfony/http-client installed, an unreachable Typesense makes `search()` throw `Symfony\Component\HttpClient\Psr18NetworkException` instead of answering a failed `Result`, where the contract says a search never throws ([omnisearch/typesense](https://github.com/glitchr-omnisense/omnisearch-typesense/blob/1.x/docs/index.md#errors)). Catch `\Throwable` around a search until it is fixed.

## Indexing

Keeping the indexes in step with the objects - `Manager::index()`, the `Indexer` and Doctrine's
listener - is on its own page: [Indexing](indexing.md).
