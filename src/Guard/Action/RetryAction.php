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

namespace MonkeysLegion\Apex\Guard\Action;

use MonkeysLegion\Apex\DTO\GuardResult;

/**
 * Retry action — flags the result for retry by the AI system.
 */
final class RetryAction
{
    public function execute(GuardResult $result): GuardResult
    {
        return new GuardResult(
            passed:     false,
            text:       $result->text,
            violations: array_merge($result->violations, ['_retry' => true]),
            validator:  $result->validator,
        );
    }
}
