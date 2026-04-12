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
 * Provider API error (4xx, 5xx, network issues).
 */
class ProviderException extends AIException
{
    public function __construct(
        string $message = 'Provider request failed',
        public readonly string $providerName = '',
        public readonly int $httpStatus = 0,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, $httpStatus, $previous);
    }

    public function getStatusCode(): int
    {
        return 502;
    }
}
