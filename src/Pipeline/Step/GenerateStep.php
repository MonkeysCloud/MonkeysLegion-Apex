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
 * Generate text via AI.
 */
final class GenerateStep implements StepInterface
{
    public function __construct(
        private readonly AI      $ai,
        private readonly ?string $system = null,
        private readonly ?string $model = null,
        private readonly string  $outputKey = 'generated',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $input = (string) ($context->get('last_output') ?? $context->input);
        $response = $this->ai->generate($input, system: $this->system, model: $this->model);
        $context->set($this->outputKey, $response->content);
        return $response->content;
    }

    public function name(): string { return 'generate'; }
}
