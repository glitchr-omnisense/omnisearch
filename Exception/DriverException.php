<?php

namespace Omnisearch\Exception;

/**
 * The engine refused or could not be reached; the code is an HTTP status
 * when the engine gave one (400 a malformed request, 404 no such index, 503
 * unreachable...).
 */
final class DriverException extends OmnisearchException
{
}
