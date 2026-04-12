<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Apply guardrail validation as a pipeline step. */
final class GuardStep implements StepInterface
{
    public function __construct(
        private readonly Guard  $guard,
        private readonly bool   $isInput = true,
        private readonly string $outputKey = 'guarded',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $text   = (string) ($context->get('last_output') ?? $context->input);
        $result = $this->isInput
            ? $this->guard->validateInput($text)
            : $this->guard->validateOutput($text);
        $context->set($this->outputKey, $result);
        return $result->text;
    }

    public function name(): string { return $this->isInput ? 'guard_input' : 'guard_output'; }
}
