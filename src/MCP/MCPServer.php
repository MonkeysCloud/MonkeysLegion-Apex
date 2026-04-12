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

namespace MonkeysLegion\Apex\MCP;

/**
 * MCP Server — provides tools and resources to MCP clients.
 */
final class MCPServer
{
    /** @var array<string, array{handler: callable, schema: array<string, mixed>}> */
    private array $tools = [];

    /** @var array<string, array{uri: string, content: string, mimeType: string}> */
    private array $resources = [];

    /**
     * Register a tool.
     *
     * @param array<string, mixed> $schema JSON Schema for the tool params.
     */
    public function tool(string $name, string $description, array $schema, callable $handler): self
    {
        $this->tools[$name] = [
            'handler'     => $handler,
            'schema'      => $schema,
            'description' => $description,
        ];
        return $this;
    }

    /**
     * Register a static resource.
     */
    public function resource(string $name, string $uri, string $content, string $mimeType = 'text/plain'): self
    {
        $this->resources[$name] = [
            'uri'      => $uri,
            'content'  => $content,
            'mimeType' => $mimeType,
        ];
        return $this;
    }

    /**
     * Process an MCP JSON-RPC request.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $method = $request['method'] ?? '';
        $id     = $request['id'] ?? null;

        return match ($method) {
            'initialize'          => $this->handleInitialize($id),
            'tools/list'          => $this->handleToolsList($id),
            'tools/call'          => $this->handleToolsCall($id, $request['params'] ?? []),
            'resources/list'      => $this->handleResourcesList($id),
            'resources/read'      => $this->handleResourcesRead($id, $request['params'] ?? []),
            default               => $this->errorResponse($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function handleInitialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => [
                    'tools'     => ['listChanged' => false],
                    'resources' => ['listChanged' => false],
                ],
                'serverInfo' => [
                    'name'    => 'MonkeysLegion-Apex',
                    'version' => '2.0.0',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleToolsList(mixed $id): array
    {
        $tools = [];
        foreach ($this->tools as $name => $tool) {
            $tools[] = [
                'name'        => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['schema'],
            ];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $tools]];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolsCall(mixed $id, array $params): array
    {
        $name      = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!is_string($name) || $name === '') {
            return $this->errorResponse($id, -32602, 'Tool name must be a non-empty string');
        }

        if (!isset($this->tools[$name])) {
            return $this->errorResponse($id, -32602, "Unknown tool: {$name}");
        }

        if (!is_array($arguments)) {
            return $this->errorResponse($id, -32602, 'Tool arguments must be an object');
        }

        // Validate arguments against the tool's input schema
        $schema = $this->tools[$name]['schema'];
        $validationError = $this->validateArguments($arguments, $schema);
        if ($validationError !== null) {
            return $this->errorResponse($id, -32602, "Invalid arguments: {$validationError}");
        }

        try {
            $result = ($this->tools[$name]['handler'])($arguments);
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => [
                    'content'  => [['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result)]],
                    'isError'  => false,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => [
                    'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                    'isError' => true,
                ],
            ];
        }
    }

    /**
     * Validate tool arguments against the JSON Schema definition.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $schema
     */
    private function validateArguments(array $arguments, array $schema): ?string
    {
        $required = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments)) {
                return "missing required field: {$field}";
            }
        }

        // Check that only declared properties are passed
        if (!empty($properties)) {
            foreach (array_keys($arguments) as $key) {
                if (!isset($properties[$key])) {
                    return "unexpected field: {$key}";
                }
            }
        }

        // Basic type checking
        foreach ($arguments as $key => $value) {
            if (!isset($properties[$key]['type'])) {
                continue;
            }
            $expected = $properties[$key]['type'];
            $actual = match (true) {
                is_string($value) => 'string',
                is_int($value)    => 'integer',
                is_float($value)  => 'number',
                is_bool($value)   => 'boolean',
                is_array($value)  => 'array',
                is_null($value)   => 'null',
                default           => 'unknown',
            };

            // Allow integer where number is expected
            if ($expected === 'number' && $actual === 'integer') {
                continue;
            }

            if ($actual !== $expected) {
                return "field '{$key}' expected {$expected}, got {$actual}";
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResourcesList(mixed $id): array
    {
        $resources = [];
        foreach ($this->resources as $name => $res) {
            $resources[] = [
                'name'     => $name,
                'uri'      => $res['uri'],
                'mimeType' => $res['mimeType'],
            ];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resources' => $resources]];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleResourcesRead(mixed $id, array $params): array
    {
        $uri = $params['uri'] ?? '';

        foreach ($this->resources as $res) {
            if ($res['uri'] === $uri) {
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'result'  => [
                        'contents' => [[
                            'uri'      => $res['uri'],
                            'text'     => $res['content'],
                            'mimeType' => $res['mimeType'],
                        ]],
                    ],
                ];
            }
        }

        return $this->errorResponse($id, -32602, "Resource not found: {$uri}");
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ];
    }
}
