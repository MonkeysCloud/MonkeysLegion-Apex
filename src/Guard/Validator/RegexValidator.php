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

namespace MonkeysLegion\Apex\Guard\Validator;

use MonkeysLegion\Apex\Contract\GuardInterface;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\Enum\GuardAction;

/**
 * Custom regex-based validator — user-defined patterns.
 */
final class RegexValidator implements GuardInterface
{
    /**
     * @param list<array{pattern: string, label: string}> $patterns
     */
    public function __construct(
        private readonly array       $patterns,
        private readonly string      $validatorName = 'regex',
        private readonly GuardAction $onFail = GuardAction::Block,
    ) {}

    public function validate(string $text): GuardResult
    {
        $violations = [];
        $redacted   = $text;

        foreach ($this->patterns as ['pattern' => $pattern, 'label' => $label]) {
            if (preg_match_all($pattern, $text, $matches)) {
                $violations[$label] = $matches[0];
                $redacted = preg_replace($pattern, '[FILTERED]', $redacted);
            }
        }

        return new GuardResult(
            passed:       empty($violations),
            text:         $text,
            redactedText: $redacted,
            violations:   $violations,
            validator:    $this->name(),
        );
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return $this->validatorName;
    }
}
