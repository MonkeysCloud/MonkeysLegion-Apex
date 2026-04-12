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
 * Result of a pipeline execution.
 */
final readonly class PipelineResult
{
    /**
     * @param list<array{step: string, output: mixed, duration_ms: float}> $trace
     * @param array<string, mixed> $data
     */
    public function __construct(
        public mixed  $output,
        public bool   $success,
        public float  $durationMs,
        public array  $trace = [],
        public array  $data = [],
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'output'      => $this->output,
            'success'     => $this->success,
            'duration_ms' => round($this->durationMs, 2),
            'steps'       => count($this->trace),
            'error'       => $this->error,
        ];
    }
}
