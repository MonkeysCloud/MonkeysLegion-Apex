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
 * Replace action — replaces text with a static replacement.
 */
final class ReplaceAction
{
    public function __construct(
        private readonly string $replacement = '[Content removed by guardrail]',
    ) {}

    public function execute(GuardResult $result): GuardResult
    {
        return new GuardResult(
            passed:      false,
            text:        $this->replacement,
            violations:  $result->violations,
            validator:   $result->validator,
            replacement: $this->replacement,
        );
    }
}
