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

/**
 * Pipeline step contract.
 */
interface StepInterface
{
    /**
     * Execute a pipeline step.
     */
    public function execute(PipelineContext $context): mixed;

    /**
     * Get step name for tracing.
     */
    public function name(): string;
}
