<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Conditional step — executes only when condition is true. */
final class ConditionalStep implements StepInterface
{
    /** @var callable(PipelineContext): bool */
    private readonly \Closure $condition;

    /** @var StepInterface|\Closure */
    private readonly StepInterface|\Closure $step;

    public function __construct(
        callable $condition,
        StepInterface|callable $innerStep,
    ) {
        $this->condition = $condition(...);
        $this->step = $innerStep instanceof StepInterface ? $innerStep : $innerStep(...);
    }

    public function execute(PipelineContext $context): mixed
    {
        if (!($this->condition)($context)) {
            return $context->get('last_output');
        }

        if ($this->step instanceof StepInterface) {
            return $this->step->execute($context);
        }

        return ($this->step)($context);
    }

    public function name(): string { return 'conditional'; }
}
