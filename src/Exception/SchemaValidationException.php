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
 * Structured output failed validation.
 */
class SchemaValidationException extends AIException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $message = 'Schema validation failed',
        public readonly array $errors = [],
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
