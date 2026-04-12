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

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/**
 * Human-in-the-loop step — pauses pipeline for human review.
 *
 * Uses a callback to present data to a human and receive approval/edits.
 * If no callback is provided, the step auto-approves.
 */
final class HumanInLoopStep implements StepInterface
{
    /** @var callable(PipelineContext): mixed|null */
    private readonly ?\Closure $reviewer;

    public function __construct(
        ?callable $reviewer = null,
        private readonly string $prompt = 'Please review the output:',
    ) {
        $this->reviewer = $reviewer !== null ? $reviewer(...) : null;
    }

    public function execute(PipelineContext $context): mixed
    {
        $lastOutput = $context->get('last_output');

        if ($this->reviewer === null) {
            // Auto-approve when no reviewer callback is set
            $context->set('human_approved', true);
            return $lastOutput;
        }

        $result = ($this->reviewer)($context);

        $context->set('human_approved', $result !== null);
        $context->set('human_review', $result);

        return $result ?? $lastOutput;
    }

    public function name(): string { return 'human_in_loop'; }
}
