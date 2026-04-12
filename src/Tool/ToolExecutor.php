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

namespace MonkeysLegion\Apex\Tool;

use MonkeysLegion\Apex\DTO\ToolCall;
use MonkeysLegion\Apex\DTO\ToolResult;
use MonkeysLegion\Apex\Exception\ToolExecutionException;

/**
 * Executes tool calls against registered tool objects.
 */
final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly float $timeout = 30.0,
    ) {}

    /**
     * Execute a single tool call.
     */
    public function execute(ToolCall $toolCall): ToolResult
    {
        $tool = $this->registry->get($toolCall->name);
        if ($tool === null) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                output: null,
                success: false,
                error: "Unknown tool: {$toolCall->name}",
            );
        }

        try {
            $method = $tool['method'];
            $args = $this->resolveArguments($method, $toolCall->arguments);
            $output = $method->invokeArgs($tool['object'], $args);

            return new ToolResult(
                toolCallId: $toolCall->id,
                output: $output,
                success: true,
            );
        } catch (\Throwable $e) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                output: null,
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Execute multiple tool calls.
     *
     * @param list<ToolCall> $toolCalls
     * @return list<ToolResult>
     */
    public function executeAll(array $toolCalls): array
    {
        return array_map(fn(ToolCall $tc) => $this->execute($tc), $toolCalls);
    }

    /**
     * Resolve method arguments from tool call arguments.
     *
     * @param array<string, mixed> $arguments
     * @return list<mixed>
     */
    private function resolveArguments(\ReflectionMethod $method, array $arguments): array
    {
        $resolved = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $arguments)) {
                $resolved[] = $arguments[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
            } else {
                throw new ToolExecutionException(
                    $method->getName(),
                    "Missing argument: {$name}",
                );
            }
        }

        return $resolved;
    }
}
