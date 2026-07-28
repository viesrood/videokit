<?php

declare(strict_types=1);

namespace viesrood\videokit\base;

use RuntimeException;

/**
 * Thrown when a provider API is unreachable, rejects our credential, or
 * answers with something we cannot use.
 *
 * Controllers translate this into a generic client-facing message; the details
 * only ever go to the Craft log.
 */
class ProviderException extends RuntimeException
{
}
