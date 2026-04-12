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

namespace MonkeysLegion\Apex\DTO;

/**
 * Guardrail validation result.
 */
final readonly class GuardResult
{
    /**
     * @param array<string, mixed> $violations
     */
    public function __construct(
        public bool    $passed,
        public string  $text,
        public ?string $redactedText = null,
        public array   $violations = [],
        public ?string $validator = null,
        public ?string $replacement = null,
        public ?int    $maxLength = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed'     => $this->passed,
            'validator'  => $this->validator,
            'violations' => $this->violations,
        ];
    }
}
