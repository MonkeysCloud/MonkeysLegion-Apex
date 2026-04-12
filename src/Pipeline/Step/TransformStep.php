<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Transform step — mutates context data at a key. */
final class TransformStep implements StepInterface
{
    /** @var callable(PipelineContext): mixed */
    private readonly \Closure $transformer;

    public function __construct(
        private readonly string $key,
        callable $transformer,
    ) {
        $this->transformer = $transformer(...);
    }

    public function execute(PipelineContext $context): mixed
    {
        $result = ($this->transformer)($context);
        $context->set($this->key, $result);
        return $result;
    }

    public function name(): string { return "transform:{$this->key}"; }
}
