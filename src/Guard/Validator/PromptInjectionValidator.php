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
 * Detects prompt injection attempts via heuristic patterns.
 */
final class PromptInjectionValidator implements GuardInterface
{
    /** @var list<string> */
    private const array PATTERNS = [
        '/ignore\s+(all\s+)?previous\s+instructions/i',
        '/disregard\s+(all\s+)?prior\s+(instructions|context)/i',
        '/you\s+are\s+now\s+(?:a|an)\s+/i',
        '/system\s*:\s*you\s+are/i',
        '/forget\s+(everything|all)/i',
        '/override\s+(the\s+)?(system|instructions)/i',
        '/\bDAN\b.*\bmode\b/i',
        '/jailbreak/i',
        '/\[INST\]/i',
        '/<\|system\|>/i',
        '/\bACT\s+AS\b/i',
        '/\bpretend\s+(?:you\s+are|to\s+be)\b/i',
        '/\brole\s*play\s+as\b/i',
        '/\bdo\s+not\s+follow\s+(?:your|the)\s+(?:rules|guidelines)\b/i',
        '/\bnew\s+instructions?\s*:/i',
        '/\b(?:bypass|circumvent|evade)\s+(?:the\s+)?(?:filter|safety|restriction)/i',
        '/\bunrestricted\s+mode\b/i',
        '/\bdev(?:eloper)?\s+mode\b/i',
    ];

    public function __construct(
        private readonly GuardAction $onFail = GuardAction::Block,
    ) {}

    public function validate(string $text): GuardResult
    {
        $found = [];

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = $matches[0];
            }
        }

        return new GuardResult(
            passed:     empty($found),
            text:       $text,
            violations: empty($found) ? [] : ['injection_patterns' => $found],
            validator:  $this->name(),
        );
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return 'prompt_injection';
    }
}
