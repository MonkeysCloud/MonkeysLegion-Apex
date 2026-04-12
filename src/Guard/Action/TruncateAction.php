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
 * Truncate action — truncates text to max length.
 */
final class TruncateAction
{
    public function execute(GuardResult $result): GuardResult
    {
        $text = $result->redactedText ?? $result->text;
        if ($result->maxLength !== null && mb_strlen($text) > $result->maxLength) {
            $text = mb_substr($text, 0, $result->maxLength);
        }

        return new GuardResult(
            passed:     false,
            text:       $text,
            violations: $result->violations,
            validator:  $result->validator,
            maxLength:  $result->maxLength,
        );
    }
}
