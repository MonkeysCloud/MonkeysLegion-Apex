<?php

declare(strict_types=1);

/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Apex\Exception;

/**
 * Internal AI rate limit exceeded.
 */
class RateLimitException extends AIException
{
    public function __construct(
        public readonly int $retryAfter = 60,
        string $message = 'AI rate limit exceeded',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return 429;
    }
}
