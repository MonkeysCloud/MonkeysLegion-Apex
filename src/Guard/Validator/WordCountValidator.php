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
 * Validates text length (word count).
 */
final class WordCountValidator implements GuardInterface
{
    public function __construct(
        private readonly ?int        $minWords = null,
        private readonly ?int        $maxWords = null,
        private readonly GuardAction $onFail = GuardAction::Truncate,
    ) {}

    public function validate(string $text): GuardResult
    {
        $words     = str_word_count($text);
        $violations = [];

        if ($this->minWords !== null && $words < $this->minWords) {
            $violations['min_words'] = [
                'expected' => $this->minWords,
                'actual'   => $words,
            ];
        }

        if ($this->maxWords !== null && $words > $this->maxWords) {
            $violations['max_words'] = [
                'expected' => $this->maxWords,
                'actual'   => $words,
            ];
        }

        // Truncate to maxWords if exceeded
        $truncated = $text;
        if ($this->maxWords !== null && $words > $this->maxWords) {
            $allWords = preg_split('/\s+/', $text);
            $truncated = implode(' ', array_slice($allWords, 0, $this->maxWords));
        }

        return new GuardResult(
            passed:       empty($violations),
            text:         $text,
            redactedText: $truncated,
            violations:   $violations,
            validator:    $this->name(),
            maxLength:    $this->maxWords !== null ? $this->maxWords * 6 : null,
        );
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return 'word_count';
    }
}
