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

namespace MonkeysLegion\Apex\Guard;

use MonkeysLegion\Apex\Contract\GuardInterface;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\Enum\GuardAction;
use MonkeysLegion\Apex\Exception\GuardException;

/**
 * Composable guardrail validation for input/output text.
 */
final class Guard
{
    /** @var list<array{validator: GuardInterface, action: GuardAction}> */
    private array $inputGuards = [];

    /** @var list<array{validator: GuardInterface, action: GuardAction}> */
    private array $outputGuards = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add input validators (pre-LLM).
     */
    public function input(GuardInterface ...$validators): self
    {
        foreach ($validators as $v) {
            $this->inputGuards[] = ['validator' => $v, 'action' => $v->defaultAction()];
        }
        return $this;
    }

    /**
     * Add output validators (post-LLM).
     */
    public function output(GuardInterface ...$validators): self
    {
        foreach ($validators as $v) {
            $this->outputGuards[] = ['validator' => $v, 'action' => $v->defaultAction()];
        }
        return $this;
    }

    /**
     * Validate input text against all input guards.
     */
    public function validateInput(string $text): GuardResult
    {
        return $this->runGuards($this->inputGuards, $text);
    }

    /**
     * Validate output text against all output guards.
     */
    public function validateOutput(string $text): GuardResult
    {
        return $this->runGuards($this->outputGuards, $text);
    }

    /**
     * @param list<array{validator: GuardInterface, action: GuardAction}> $guards
     */
    private function runGuards(array $guards, string $text): GuardResult
    {
        $violations = [];
        $modified   = $text;

        foreach ($guards as ['validator' => $validator, 'action' => $action]) {
            $result = $validator->validate($modified);

            if (!$result->passed) {
                $violations[] = $result;

                $modified = match ($action) {
                    GuardAction::Block    => throw new GuardException($result),
                    GuardAction::Redact   => $result->redactedText ?? $modified,
                    GuardAction::Retry    => $modified,
                    GuardAction::Warn     => $modified,
                    GuardAction::Truncate => substr($modified, 0, $result->maxLength ?? 500),
                    GuardAction::Replace  => $result->replacement ?? $modified,
                    GuardAction::Pass     => $modified,
                };
            }
        }

        return new GuardResult(
            passed:     empty($violations),
            text:       $modified,
            violations: $violations,
        );
    }
}
