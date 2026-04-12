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
 * AI request timed out.
 */
class TimeoutException extends AIException
{
    public function __construct(
        public readonly float $timeoutSeconds,
        string $message = 'AI request timed out',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return 504;
    }
}
