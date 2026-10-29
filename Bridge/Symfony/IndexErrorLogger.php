<?php

namespace Omnisearch\Bridge\Symfony;

use Psr\Log\LoggerInterface;

/**
 * The bundle's Indexer $onError by default (on_index_error: log): a write an
 * engine refuses, or that cannot reach it, during Doctrine's flush is logged
 * as an error, the exception in the context, and the application's flush
 * goes on - the database has committed by then. Without a logger it is left
 * to PHP's error log.
 */
final class IndexErrorLogger
{
    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    public function __invoke(\Throwable $e): void
    {
        $message = 'Omnisearch: an index write failed: '.$e->getMessage();
        if (null === $this->logger) {
            error_log($message);

            return;
        }
        $this->logger->error($message, ['exception' => $e]);
    }
}
