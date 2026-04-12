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

namespace MonkeysLegion\Apex\Pipeline;

use MonkeysLegion\Apex\Exception\PipelineException;

/**
 * Declarative AI pipeline builder and executor.
 */
final class Pipeline
{
    /** @var list<StepInterface|callable> */
    private array $steps = [];

    private ?string $name = null;

    public static function create(?string $name = null): self
    {
        $pipeline = new self();
        $pipeline->name = $name;
        return $pipeline;
    }

    /**
     * Add a step to the pipeline.
     */
    public function pipe(StepInterface|callable $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * Add a conditional step.
     *
     * @param callable(PipelineContext): bool $condition
     */
    public function when(callable $condition, StepInterface|callable $step): self
    {
        $this->steps[] = new Step\ConditionalStep($condition, $step);
        return $this;
    }

    /**
     * Add a loop step.
     *
     * @param callable(PipelineContext): bool $condition While condition is true.
     */
    public function loop(callable $condition, StepInterface|callable $step, int $maxIterations = 10): self
    {
        $this->steps[] = new Step\LoopStep($condition, $step, $maxIterations);
        return $this;
    }

    /**
     * Add a transform step — mutates context data.
     *
     * @param callable(PipelineContext): mixed $transformer
     */
    public function transform(string $key, callable $transformer): self
    {
        $this->steps[] = new Step\TransformStep($key, $transformer);
        return $this;
    }

    /**
     * Execute the pipeline.
     */
    public function run(string $input, string $model = ''): PipelineResult
    {
        $context = new PipelineContext($input, $model);
        $context->set('input', $input);

        try {
            $output = null;

            foreach ($this->steps as $step) {
                $start = hrtime(true);

                if ($step instanceof StepInterface) {
                    $output = $step->execute($context);
                    $context->trace($step->name(), $output, (hrtime(true) - $start) / 1e6);
                } elseif (is_callable($step)) {
                    $output = $step($context);
                    $context->trace('callable', $output, (hrtime(true) - $start) / 1e6);
                }

                $context->set('last_output', $output);
            }

            return new PipelineResult(
                output:     $output,
                success:    true,
                durationMs: $context->elapsedMs(),
                trace:      $context->getTrace(),
                data:       $context->all(),
            );
        } catch (\Throwable $e) {
            return new PipelineResult(
                output:     null,
                success:    false,
                durationMs: $context->elapsedMs(),
                trace:      $context->getTrace(),
                data:       $context->all(),
                error:      $e->getMessage(),
            );
        }
    }
}
