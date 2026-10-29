<?php

namespace Omnisearch\Exception;

/**
 * Every exception omnisearch and its drivers throw: an engine refusing or
 * not reached (DriverException), a connection or a driver not configured
 * (InvalidConfigException), an index declared in a way that cannot work
 * (MappingException). A call that can never work as written - a Filter with
 * an unknown operator, or "in" without a list - is PHP's
 * \InvalidArgumentException, thrown at once: a mistake in the code, not a
 * failure to catch.
 */
class OmnisearchException extends \RuntimeException
{
}
