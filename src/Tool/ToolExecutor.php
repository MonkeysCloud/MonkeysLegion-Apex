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
    /** @var array<string, list<array{name: string, hasDefault: bool, default: mixed}>> */
    private array $paramCache = [];

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
     * Uses parallel execution via pcntl_fork when the ext-pcntl extension
     * is available and there are multiple tool calls. Falls back to
     * sequential execution otherwise.
     *
     * @param list<ToolCall> $toolCalls
     * @return list<ToolResult>
     */
    public function executeAll(array $toolCalls): array
    {
        // Use parallel execution only when we have multiple calls and pcntl
        if (count($toolCalls) > 1 && function_exists('pcntl_fork') && function_exists('socket_create_pair')) {
            return $this->executeParallel($toolCalls);
        }

        return array_map(fn(ToolCall $tc) => $this->execute($tc), $toolCalls);
    }

    /**
     * Execute tool calls in parallel using pcntl_fork.
     *
     * @param list<ToolCall> $toolCalls
     * @return list<ToolResult>
     */
    private function executeParallel(array $toolCalls): array
    {
        /** @var array<int, array{pid: int, socket: \Socket, index: int}> $children */
        $children = [];
        $results  = array_fill(0, count($toolCalls), null);

        foreach ($toolCalls as $i => $tc) {
            $pair = [];
            if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair)) {
                // Fallback to sequential on socket failure
                return array_map(fn(ToolCall $tc) => $this->execute($tc), $toolCalls);
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                // Fork failed — fallback to sequential
                socket_close($pair[0]);
                socket_close($pair[1]);
                return array_map(fn(ToolCall $tc) => $this->execute($tc), $toolCalls);
            }

            if ($pid === 0) {
                // Child process
                socket_close($pair[0]);
                $result = $this->execute($tc);
                $data = serialize($result);
                socket_write($pair[1], $data, strlen($data));
                socket_close($pair[1]);
                // Use _exit to avoid running shutdown handlers
                exit(0);
            }

            // Parent process
            socket_close($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0], 'index' => $i];
        }

        // Collect results from children
        foreach ($children as $child) {
            $data = '';
            while (($chunk = socket_read($child['socket'], 65536)) !== false && $chunk !== '') {
                $data .= $chunk;
            }
            socket_close($child['socket']);

            pcntl_waitpid($child['pid'], $status);

            if ($data !== '') {
                $result = @unserialize($data);
                if ($result instanceof ToolResult) {
                    $results[$child['index']] = $result;
                }
            }

            // If deserialization failed, create an error result
            if ($results[$child['index']] === null) {
                $results[$child['index']] = new ToolResult(
                    toolCallId: $toolCalls[$child['index']]->id,
                    output: null,
                    success: false,
                    error: 'Parallel execution failed for tool',
                );
            }
        }

        /** @var list<ToolResult> */
        return $results;
    }

    /**
     * Resolve method arguments from tool call arguments.
     * Uses cached parameter metadata to avoid repeated reflection.
     *
     * @param array<string, mixed> $arguments
     * @return list<mixed>
     */
    private function resolveArguments(\ReflectionMethod $method, array $arguments): array
    {
        $cacheKey = $method->getDeclaringClass()->getName() . '::' . $method->getName();

        if (!isset($this->paramCache[$cacheKey])) {
            $params = [];
            foreach ($method->getParameters() as $param) {
                $params[] = [
                    'name'       => $param->getName(),
                    'hasDefault' => $param->isDefaultValueAvailable(),
                    'default'    => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                ];
            }
            $this->paramCache[$cacheKey] = $params;
        }

        $resolved = [];
        foreach ($this->paramCache[$cacheKey] as $paramInfo) {
            $name = $paramInfo['name'];
            if (array_key_exists($name, $arguments)) {
                $resolved[] = $arguments[$name];
            } elseif ($paramInfo['hasDefault']) {
                $resolved[] = $paramInfo['default'];
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
