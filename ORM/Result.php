<?php

namespace Omnisearch\ORM;

use Omnisearch\DBAL\Facet;
use Omnisearch\DBAL\Hit;
use Omnisearch\DBAL\Response;

/**
 * A search's answer for the application: the engine's Response, and the
 * objects of its hits once loaded - in the order of the hits; a hit whose
 * object is gone is left out.
 */
final class Result
{
    /** @var \WeakMap<object, Hit> */
    private \WeakMap $hitOf;

    /**
     * @param list<object> $objects
     */
    public function __construct(
        public readonly Response $response,
        private readonly array $objects = [],
        private readonly ?\Closure $identify = null,
    ) {
        $this->hitOf = new \WeakMap();
        if ($objects && $identify) {
            $hits = [];
            foreach ($response->hits as $hit) {
                $hits[$hit->id] = $hit;
            }
            foreach ($objects as $object) {
                if (isset($hits[$id = $identify($object)])) {
                    $this->hitOf[$object] = $hits[$id];
                }
            }
        }
    }

    public function ok(): bool
    {
        return $this->response->ok();
    }

    public function status(): int
    {
        return $this->response->status;
    }

    public function error(): ?string
    {
        return $this->response->error;
    }

    public function found(): int
    {
        return $this->response->found;
    }

    public function page(): int
    {
        return $this->response->page;
    }

    public function totalPages(int $perPage): int
    {
        return (int) ceil($this->response->found / max(1, $perPage));
    }

    /** @return list<Hit> */
    public function hits(): array
    {
        return $this->response->hits;
    }

    /** @return list<object> */
    public function objects(): array
    {
        return $this->objects;
    }

    /** The hit an object was loaded from: its document, its matched words. */
    public function hit(object $object): ?Hit
    {
        return $this->hitOf[$object] ?? null;
    }

    /**
     * The facets counted, by field: each value with its count and whether
     * the visitor checked it.
     *
     * @param array<string, list<string>> $checked     the values checked, by field
     * @param bool                        $sortByValue in the order of the values rather than by count
     *
     * @return array<string, array{field: string, counts: array<string, array{value: string, count: int, checked: bool}>, extra: array<string, mixed>}>
     */
    public function facets(array $checked = [], bool $sortByValue = false): array
    {
        return self::facetsOf($this->response, $checked, $sortByValue);
    }

    /** @return array<string, array{field: string, counts: array<string, array<string, mixed>>, extra: array<string, mixed>}> */
    public static function facetsOf(Response $response, array $checked = [], bool $sortByValue = false): array
    {
        $facets = [];
        foreach ($response->facets as $field => $facet) {
            /** @var Facet $facet */
            $values = $facet->values;
            if ($sortByValue) {
                usort($values, fn ($a, $b) => strcmp((string) ($a['value'] ?? ''), (string) ($b['value'] ?? '')));
            }
            $counts = [];
            foreach ($values as $value) {
                $counts[(string) $value['value']] = array_merge($value, ['checked' => \in_array($value['value'], $checked[$field] ?? [])]);
            }
            $facets[$field] = ['field' => $field, 'counts' => $counts, 'extra' => $facet->extra];
        }

        return $facets;
    }
}
