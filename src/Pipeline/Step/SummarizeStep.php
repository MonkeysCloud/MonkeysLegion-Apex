<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Summarize text via AI. */
final class SummarizeStep implements StepInterface
{
    public function __construct(
        private readonly AI      $ai,
        private readonly ?int    $maxWords = null,
        private readonly string  $outputKey = 'summary',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $input  = (string) ($context->get('last_output') ?? $context->input);
        $prompt = $this->maxWords
            ? "Summarize the following text in {$this->maxWords} words or less:\n\n{$input}"
            : "Summarize the following text concisely:\n\n{$input}";
        $response = $this->ai->generate($prompt);
        $context->set($this->outputKey, $response->content);
        return $response->content;
    }

    public function name(): string { return 'summarize'; }
}
