<?php

namespace Omnisearch\ORM;

use Omnisearch\DBAL\Connection;
use Omnisearch\DBAL\Request;
use Omnisearch\DBAL\Response;
use Omnisearch\Mapping\ClassMetadata;

/**
 * The searches of one index: its documents (raw()), its objects (search(),
 * loaded from the ObjectStore in the order of the hits), its facets.
 */
final class Finder
{
    public function __construct(
        private readonly Connection $connection,
        public readonly ClassMetadata $metadata,
        private readonly ?ObjectStore $store = null,
    ) {
    }

    public function raw(Query|Request $query): Response
    {
        return $this->connection->search($this->metadata, $query instanceof Query ? $query->request() : $query);
    }

    public function search(Query|Request $query): Result
    {
        $request = $query instanceof Query ? $query->request() : $query;
        $response = $this->raw($request);
        if (!$this->store || !$response->ok() || !$response->hits) {
            return new Result($response);
        }

        return new Result($response, $this->store->load($this->metadata, $response->ids(), $request->cacheable), $this->store->identify(...));
    }

    /**
     * The values of these fields with their counts, among the documents
     * $query finds (all of them when null).
     *
     * @param list<string>                $fields
     * @param array<string, list<string>> $checked
     *
     * @return array<string, array{field: string, counts: array<string, array<string, mixed>>, extra: array<string, mixed>}>
     */
    public function facets(array $fields, ?Query $query = null, array $checked = [], bool $sortByValue = false): array
    {
        $query = $query ? clone $query : (new Query())->in(...$fields);
        $query->facet(...$fields)->perPage(0);

        return Result::facetsOf($this->raw($query), $checked, $sortByValue);
    }
}
