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

use MonkeysLegion\Apex\Event\EventDispatcher;
use Psr\Log\LoggerInterface;

/**
 * Pipeline runner — executes named pipelines with logging and events.
 */
final class PipelineRunner
{
    /** @var array<string, Pipeline> */
    private array $pipelines = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?EventDispatcher $events = null,
    ) {}

    /**
     * Register a pipeline.
     */
    public function register(string $name, Pipeline $pipeline): self
    {
        $this->pipelines[$name] = $pipeline;
        return $this;
    }

    /**
     * Run a registered pipeline by name.
     */
    public function run(string $name, string $input, string $model = ''): PipelineResult
    {
        if (!isset($this->pipelines[$name])) {
            return new PipelineResult(
                output:     null,
                success:    false,
                durationMs: 0,
                trace:      [],
                data:       [],
                error:      "Pipeline not found: {$name}",
            );
        }

        $this->logger?->info("Pipeline [{$name}] starting", ['input_length' => strlen($input)]);

        $result = $this->pipelines[$name]->run($input, $model);

        if ($result->success) {
            $this->logger?->info("Pipeline [{$name}] completed", [
                'duration_ms' => $result->durationMs,
                'steps'       => count($result->trace),
            ]);
        } else {
            $this->logger?->error("Pipeline [{$name}] failed", [
                'error'       => $result->error,
                'duration_ms' => $result->durationMs,
            ]);
        }

        return $result;
    }

    /**
     * Run multiple pipelines sequentially, chaining output → input.
     *
     * @param list<string> $names
     */
    public function chain(array $names, string $input, string $model = ''): PipelineResult
    {
        $lastResult = null;
        $currentInput = $input;

        foreach ($names as $name) {
            $lastResult = $this->run($name, $currentInput, $model);
            if (!$lastResult->success) {
                return $lastResult;
            }
            $currentInput = (string) $lastResult->output;
        }

        return $lastResult ?? new PipelineResult(
            output: null, success: false, durationMs: 0, trace: [], data: [], error: 'No pipelines provided',
        );
    }

    /**
     * List registered pipelines.
     *
     * @return list<string>
     */
    public function list(): array
    {
        return array_keys($this->pipelines);
    }
}
