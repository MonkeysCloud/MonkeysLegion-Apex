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

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/**
 * Classify text into categories.
 */
final class ClassifyStep implements StepInterface
{
    /**
     * @param list<string> $categories
     */
    public function __construct(
        private readonly AI     $ai,
        private readonly array  $categories,
        private readonly string $outputKey = 'classification',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $input = (string) ($context->get('last_output') ?? $context->input);
        $cats  = implode(', ', $this->categories);
        $response = $this->ai->generate(
            "Classify the following text into exactly one of these categories: {$cats}\n\nText: {$input}\n\nRespond with ONLY the category name, nothing else.",
        );
        $result = trim($response->content);
        $context->set($this->outputKey, $result);
        return $result;
    }

    public function name(): string { return 'classify'; }
}
