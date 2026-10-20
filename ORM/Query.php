<?php

namespace Omnisearch\ORM;

use Omnisearch\DBAL\Filter;
use Omnisearch\DBAL\Request;

/**
 * A search, written fluently and the same for every engine:
 *
 *     $query = (new Query('fuji'))
 *         ->in('title', 'excerpt', 'content')
 *         ->where('state', 'publish')
 *         ->where('tags', 'en-musique')
 *         ->facet('tags')
 *         ->sortBy('publishedAt', 'desc')
 *         ->page(2)->perPage(10);
 *
 * An engine's own parameters go through option() under its names
 * (->option('infix', 'always') for Typesense).
 */
final class Query
{
    /** @var list<string> */
    private array $fields = [];
    /** @var list<Filter> */
    private array $filters = [];
    /** @var list<string> */
    private array $facets = [];
    private ?int $maxFacetValues = null;
    /** @var list<array{0: string, 1: 'asc'|'desc'}> */
    private array $sort = [];
    private int $page = 1;
    private ?int $perPage = null;
    /** @var array<string, mixed> */
    private array $options = [];
    private bool $cacheable = false;

    public function __construct(private ?string $term = null)
    {
    }

    public function term(?string $term): self
    {
        $this->term = $term;

        return $this;
    }

    /** The fields the term is searched in, in order of importance. */
    public function in(string ...$fields): self
    {
        array_push($this->fields, ...$fields);

        return $this;
    }

    /** The field holds this value (one of its values, for a list). */
    public function where(string $field, string|int|float|bool $value): self
    {
        return $this->filter(Filter::equals($field, $value));
    }

    public function whereNot(string $field, string|int|float|bool $value): self
    {
        return $this->filter(Filter::notEquals($field, $value));
    }

    /** @param list<string|int|float|bool> $values */
    public function whereIn(string $field, array $values): self
    {
        return $this->filter(Filter::in($field, $values));
    }

    public function filter(Filter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /** The fields whose values are counted among the documents found. */
    public function facet(string ...$fields): self
    {
        array_push($this->facets, ...$fields);

        return $this;
    }

    public function maxFacetValues(?int $max): self
    {
        $this->maxFacetValues = $max;

        return $this;
    }

    /** @param 'asc'|'desc' $direction */
    public function sortBy(string $field, string $direction = 'asc'): self
    {
        $this->sort[] = [$field, 'desc' === strtolower($direction) ? 'desc' : 'asc'];

        return $this;
    }

    public function page(int $page): self
    {
        $this->page = max(1, $page);

        return $this;
    }

    public function perPage(?int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function option(string $name, mixed $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    /** The engine's answer, and the objects loaded from it, may be kept in the cache. */
    public function cacheable(bool $cacheable = true): self
    {
        $this->cacheable = $cacheable;

        return $this;
    }

    public function request(): Request
    {
        return new Request($this->term, $this->fields, $this->filters, $this->facets, $this->maxFacetValues, $this->sort, $this->page, $this->perPage, $this->options, $this->cacheable);
    }
}
