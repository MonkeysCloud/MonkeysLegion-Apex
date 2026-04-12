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
use MonkeysLegion\Apex\Schema\Schema;

/**
 * Extract structured data via AI.
 *
 * @template T of Schema
 */
final class ExtractStep implements StepInterface
{
    /**
     * @param class-string<T> $schema
     */
    public function __construct(
        private readonly AI     $ai,
        private readonly string $schema,
        private readonly string $outputKey = 'extracted',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $input  = (string) ($context->get('last_output') ?? $context->input);
        $result = $this->ai->extract($this->schema, $input);
        $context->set($this->outputKey, $result);
        return $result;
    }

    public function name(): string { return 'extract'; }
}
