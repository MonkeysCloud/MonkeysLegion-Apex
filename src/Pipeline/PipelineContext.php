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
 * Shared context passed through a pipeline.
 */
final class PipelineContext
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var list<array{step: string, output: mixed, duration_ms: float}> */
    private array $trace = [];

    private float $startedAt;

    public function __construct(
        public readonly string $input,
        public readonly string $model = '',
    ) {
        $this->startedAt = hrtime(true) / 1e6;
    }

    /**
     * Set a value in context.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Get a value from context.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Record a step execution.
     */
    public function trace(string $step, mixed $output, float $durationMs): void
    {
        $this->trace[] = [
            'step'        => $step,
            'output'      => $output,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * @return list<array{step: string, output: mixed, duration_ms: float}>
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function elapsedMs(): float
    {
        return (hrtime(true) / 1e6) - $this->startedAt;
    }
}
