<?php

namespace Omnisearch\Memory;

use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\Facet;
use Omnisearch\DBAL\Filter;
use Omnisearch\DBAL\Hit;
use Omnisearch\DBAL\Request;
use Omnisearch\DBAL\Response;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\FieldMetadata;
use Omnisearch\ORM\Transformer;

/**
 * An engine in the PHP process itself: the documents in an array, a term
 * matched word by word (a word of the term matches a word of a field that
 * starts with it, or that holds it anywhere in an infix field), filters,
 * facets, sort, pages. For tests and for an application's first steps; it
 * keeps nothing once the process ends.
 */
final class MemoryDriver implements Driver
{
    /** @var array<string, array<string, array<string, mixed>>> documents by index, by id */
    private array $indexes = [];

    public function __construct(private readonly Transformer $transformer)
    {
    }

    public function create(ClassMetadata $metadata): void
    {
        $this->indexes[$metadata->index] ??= [];
    }

    public function drop(ClassMetadata $metadata): void
    {
        unset($this->indexes[$metadata->index]);
    }

    public function schema(ClassMetadata $metadata): array
    {
        return ['name' => $metadata->index, 'fields' => array_values(array_map(fn (FieldMetadata $f) => ['name' => $f->name, 'type' => $f->type, 'facet' => $f->facet, 'infix' => $f->infix], $metadata->fields))];
    }

    public function index(ClassMetadata $metadata, object ...$objects): void
    {
        foreach ($objects as $object) {
            $document = $this->transformer->document($metadata, $object);
            $this->indexes[$metadata->index][$document['id']] = $document;
        }
    }

    public function remove(ClassMetadata $metadata, string ...$ids): void
    {
        foreach ($ids as $id) {
            unset($this->indexes[$metadata->index][$id]);
        }
    }

    public function search(ClassMetadata $metadata, Request $request): Response
    {
        if (!isset($this->indexes[$metadata->index])) {
            return Response::failed(404, \sprintf('No index "%s".', $metadata->index));
        }

        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) $request->term))) ?: [], fn ($w) => '' !== $w && '*' !== $w));
        $fields = $request->fields ?: array_keys($metadata->fields);
        $hits = [];
        foreach ($this->indexes[$metadata->index] as $id => $document) {
            if ($request->filters && array_filter($request->filters, fn (Filter $f) => !$f->matches($document[$f->field] ?? null))) {
                continue;
            }
            $highlights = [];
            foreach ($words as $word) {
                $matched = false;
                foreach ($fields as $name) {
                    $infix = (bool) $metadata->field($name)?->infix;
                    foreach ((array) ($document[$name] ?? []) as $value) {
                        foreach (preg_split('/[^\p{L}\p{N}]+/u', (string) $value) ?: [] as $token) {
                            $lower = mb_strtolower($token);
                            if ('' !== $lower && (str_starts_with($lower, $word) || ($infix && str_contains($lower, $word)))) {
                                $highlights[$name][] = $token;
                                $matched = true;
                            }
                        }
                    }
                }
                if (!$matched) {
                    continue 2;
                }
            }
            $hits[] = new Hit((string) $id, $document, array_map(fn ($t) => array_values(array_unique($t)), $highlights));
        }

        foreach (array_reverse($request->sort) as [$field, $direction]) {
            usort($hits, fn (Hit $a, Hit $b) => ('desc' === $direction ? -1 : 1) * (($a->document[$field] ?? null) <=> ($b->document[$field] ?? null)));
        }

        $facets = [];
        foreach ($request->facets as $field) {
            $counts = [];
            foreach ($hits as $hit) {
                foreach ((array) ($hit->document[$field] ?? []) as $value) {
                    $value = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            arsort($counts);
            $values = [];
            foreach (\array_slice($counts, 0, $request->maxFacetValues ?? 10, true) as $value => $count) {
                $values[] = ['value' => (string) $value, 'count' => $count];
            }
            $facets[$field] = new Facet($field, $values);
        }

        $perPage = $request->perPage ?? 10;

        return new Response(\count($hits), $request->page, \array_slice($hits, ($request->page - 1) * $perPage, $perPage), $facets);
    }
}
