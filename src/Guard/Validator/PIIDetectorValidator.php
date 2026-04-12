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
 * Detects and optionally redacts PII in text.
 */
final class PIIDetectorValidator implements GuardInterface
{
    /** @var array<string, string> Entity → regex pattern */
    private const array PATTERNS = [
        'email'       => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        'phone'       => '/(?:\+?\d{1,3}[\s.-]?)?\(?\d{2,4}\)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}\b/',
        'ssn'         => '/\b\d{3}-\d{2}-\d{4}\b/',
        'credit_card' => '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        'ip_address'  => '/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\b/',
    ];

    /**
     * @param list<string> $entities Which PII types to detect.
     */
    public function __construct(
        private readonly array $entities = ['email', 'phone', 'ssn', 'credit_card'],
        private readonly string $mask = '[REDACTED]',
        private readonly GuardAction $onFail = GuardAction::Redact,
    ) {}

    public function validate(string $text): GuardResult
    {
        $found    = [];
        $redacted = $text;

        foreach ($this->entities as $entity) {
            if (isset(self::PATTERNS[$entity])) {
                if (preg_match_all(self::PATTERNS[$entity], $text, $matches)) {
                    $found[$entity] = $matches[0];
                    $redacted = preg_replace(
                        self::PATTERNS[$entity],
                        $this->mask,
                        $redacted,
                    );
                }
            }
        }

        return new GuardResult(
            passed:       empty($found),
            text:         $text,
            redactedText: $redacted,
            violations:   $found,
            validator:    $this->name(),
        );
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return 'pii_detector';
    }
}
