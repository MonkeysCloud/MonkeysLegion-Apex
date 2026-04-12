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
 * Redact action — returns text with violations redacted.
 */
final class RedactAction
{
    public function execute(GuardResult $result): GuardResult
    {
        return new GuardResult(
            passed:       false,
            text:         $result->redactedText ?? $result->text,
            redactedText: $result->redactedText,
            violations:   $result->violations,
            validator:    $result->validator,
        );
    }
}
