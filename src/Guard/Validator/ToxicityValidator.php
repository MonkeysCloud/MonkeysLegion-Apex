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
 * Detects toxic language using keyword and pattern matching.
 */
final class ToxicityValidator implements GuardInterface
{
    /** @var list<string> */
    private const array TOXIC_PATTERNS = [
        '/\b(hate|kill|murder|attack)\s+(all|every|those)\b/i',
        '/\b(slur|racial|racist|sexist|homophobic)\b/i',
        '/\bdeath\s+threat/i',
        '/\b(harass|stalk|bully|threaten)\b/i',
    ];

    /** @var list<string> */
    private array $customPatterns;

    /**
     * @param list<string> $customPatterns Additional regex patterns.
     */
    public function __construct(
        array $customPatterns = [],
        private readonly float $threshold = 0.5,
        private readonly GuardAction $onFail = GuardAction::Block,
    ) {
        $this->customPatterns = $customPatterns;
    }

    public function validate(string $text): GuardResult
    {
        $allPatterns = array_merge(self::TOXIC_PATTERNS, $this->customPatterns);
        $matches     = [];

        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $matches[] = $m[0];
            }
        }

        $score = count($matches) > 0 ? min(1.0, count($matches) * 0.3) : 0.0;

        return new GuardResult(
            passed:     $score < $this->threshold,
            text:       $text,
            violations: empty($matches) ? [] : ['toxic_patterns' => $matches, 'score' => $score],
            validator:  $this->name(),
        );
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return 'toxicity';
    }
}
