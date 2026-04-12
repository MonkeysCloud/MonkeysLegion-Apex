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
 * Parallel step — runs multiple steps concurrently (simulated).
 *
 * Each step receives the same context snapshot. Results are stored
 * in the context under the 'parallel_results' key.
 */
final class ParallelStep implements StepInterface
{
    /** @var list<StepInterface|callable> */
    private readonly array $steps;

    public function __construct(StepInterface|callable ...$steps)
    {
        $this->steps = $steps;
    }

    public function execute(PipelineContext $context): mixed
    {
        $results = [];

        foreach ($this->steps as $i => $step) {
            $results[$i] = $step instanceof StepInterface
                ? $step->execute($context)
                : $step($context);
        }

        $context->set('parallel_results', $results);
        return $results;
    }

    public function name(): string { return 'parallel'; }
}
