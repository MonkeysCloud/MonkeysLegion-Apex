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

/**
 * Runs a list of validators in sequence, applying actions on failure.
 */
final class GuardPipeline
{
    /** @var list<array{validator: GuardInterface, action: GuardAction}> */
    private array $validators = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a validator with a specific action.
     */
    public function add(GuardInterface $validator, ?GuardAction $action = null): self
    {
        $this->validators[] = [
            'validator' => $validator,
            'action'    => $action ?? $validator->defaultAction(),
        ];
        return $this;
    }

    /**
     * Run all validators against the text.
     *
     * @return list<GuardResult>
     */
    public function validate(string $text): array
    {
        $results = [];

        foreach ($this->validators as ['validator' => $validator, 'action' => $action]) {
            $result = $validator->validate($text);
            if (!$result->passed) {
                $result = $this->applyAction($result, $action);
                // Use redacted text for subsequent validators
                $text = $result->redactedText ?? $result->text;
            }
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Run all validators; return true only if all pass.
     */
    public function passes(string $text): bool
    {
        foreach ($this->validators as ['validator' => $validator]) {
            if (!$validator->validate($text)->passed) {
                return false;
            }
        }
        return true;
    }

    private function applyAction(GuardResult $result, GuardAction $action): GuardResult
    {
        return match ($action) {
            GuardAction::Block    => (new Action\BlockAction())->execute($result),
            GuardAction::Redact   => (new Action\RedactAction())->execute($result),
            GuardAction::Warn     => (new Action\WarnAction())->execute($result),
            GuardAction::Truncate => (new Action\TruncateAction())->execute($result),
            GuardAction::Replace  => (new Action\ReplaceAction())->execute($result),
            GuardAction::Retry    => (new Action\RetryAction())->execute($result),
            GuardAction::Pass     => $result,
        };
    }
}
