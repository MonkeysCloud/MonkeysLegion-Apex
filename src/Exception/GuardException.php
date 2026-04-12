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

use MonkeysLegion\Apex\DTO\GuardResult;

/**
 * Guardrail blocked the request or response.
 */
class GuardException extends AIException
{
    public function __construct(
        public readonly GuardResult $result,
        string $message = 'Guardrail validation failed',
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
