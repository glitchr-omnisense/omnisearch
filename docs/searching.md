---
title: Searching
order: 3
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

## Indexing

`Indexer` is the rule, whatever persistence calls it: `persisted()`, `updated()`, `removing()`
(the identifier taken before the object goes), `removed()`, then `flush()` sends it all as a
`Batch`. A failure to reach an engine goes to its `$onError` (a log, a flash message) and does not
stop the application's own flush. `Bridge\Doctrine\IndexListener` calls it from Doctrine's events.
`$manager->index(...$objects)` indexes now; `$manager->create()` and `drop()` make and remove the
indexes.
