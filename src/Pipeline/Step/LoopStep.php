<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Loop step — repeats while condition is true (max iterations). */
final class LoopStep implements StepInterface
{
    /** @var callable(PipelineContext): bool */
    private readonly \Closure $condition;

    /** @var StepInterface|\Closure */
    private readonly StepInterface|\Closure $step;

    public function __construct(
        callable $condition,
        StepInterface|callable $innerStep,
        private readonly int $maxIterations = 10,
    ) {
        $this->condition = $condition(...);
        $this->step = $innerStep instanceof StepInterface ? $innerStep : $innerStep(...);
    }

    public function execute(PipelineContext $context): mixed
    {
        $output = null;
        $i      = 0;

        while (($this->condition)($context) && $i < $this->maxIterations) {
            $output = $this->step instanceof StepInterface
                ? $this->step->execute($context)
                : ($this->step)($context);
            $context->set('last_output', $output);
            $context->set('loop_iteration', ++$i);
        }

        return $output;
    }

    public function name(): string { return 'loop'; }
}
