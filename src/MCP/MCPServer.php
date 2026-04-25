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
 * MCP Server — provides tools, resources, and prompts to MCP clients.
 *
 * Supports MCP protocol versions:
 *   - 2024-11-05 (legacy, for backwards compatibility)
 *   - 2025-03-26
 *   - 2025-11-25 (latest — Streamable HTTP transport)
 */
final class MCPServer
{
    public const string LATEST_PROTOCOL = '2025-11-25';

    private const array SUPPORTED_VERSIONS = ['2024-11-05', '2025-03-26', '2025-11-25'];

    /** @var array<string, array{handler: callable, schema: array<string, mixed>, description: string}> */
    private array $tools = [];

    /** @var array<string, array{uri: string, content: string, mimeType: string}> */
    private array $resources = [];

    /** @var array<string, MCPPrompt> */
    private array $prompts = [];

    /** @var ?string Current session ID */
    private ?string $sessionId = null;

    /** @var string Negotiated protocol version */
    private string $protocolVersion = self::LATEST_PROTOCOL;

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
     * Register a prompt template.
     */
    public function prompt(MCPPrompt $prompt): self
    {
        $this->prompts[$prompt->name] = $prompt;
        return $this;
    }

    /**
     * Process an MCP JSON-RPC request.
     *
     * Supports Streamable HTTP transport (single endpoint for POST/GET).
     *
     * @param array<string, mixed> $request
     * @param array<string, string> $headers Request headers (for version negotiation)
     * @return array<string, mixed>
     */
    public function handle(array $request, array $headers = []): array
    {
        // Protocol version negotiation
        $this->negotiateVersion($headers);

        $method = $request['method'] ?? '';
        $id     = $request['id'] ?? null;

        return match ($method) {
            'initialize'          => $this->handleInitialize($id, $request['params'] ?? []),
            'tools/list'          => $this->handleToolsList($id),
            'tools/call'          => $this->handleToolsCall($id, $request['params'] ?? []),
            'resources/list'      => $this->handleResourcesList($id),
            'resources/read'      => $this->handleResourcesRead($id, $request['params'] ?? []),
            'prompts/list'        => $this->handlePromptsList($id),
            'prompts/get'         => $this->handlePromptsGet($id, $request['params'] ?? []),
            'ping'                => $this->handlePing($id),
            default               => $this->errorResponse($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * Get response headers for the Streamable HTTP transport.
     *
     * @return array<string, string>
     */
    public function responseHeaders(): array
    {
        $headers = [
            'Content-Type'         => 'application/json',
            'MCP-Protocol-Version' => $this->protocolVersion,
        ];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }

    /**
     * Get current session ID.
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Negotiate the protocol version from request headers.
     *
     * @param array<string, string> $headers
     */
    private function negotiateVersion(array $headers): void
    {
        $requested = $headers['MCP-Protocol-Version']
            ?? $headers['mcp-protocol-version']
            ?? null;

        if ($requested !== null && in_array($requested, self::SUPPORTED_VERSIONS, true)) {
            $this->protocolVersion = $requested;
        }

        // Session management
        $sessionId = $headers['MCP-Session-Id']
            ?? $headers['mcp-session-id']
            ?? null;

        if ($sessionId !== null) {
            $this->sessionId = $sessionId;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleInitialize(mixed $id, array $params): array
    {
        // Generate session ID for stateful connections
        $this->sessionId = bin2hex(random_bytes(16));

        // Determine capabilities based on protocol version
        $capabilities = [
            'tools'     => ['listChanged' => false],
            'resources' => ['listChanged' => false],
        ];

        // Prompts support added in 2025-03-26+
        if ($this->protocolVersion !== '2024-11-05') {
            $capabilities['prompts'] = ['listChanged' => false];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => $this->protocolVersion,
                'capabilities'    => $capabilities,
                'serverInfo'      => [
                    'name'    => 'MonkeysLegion-Apex',
                    'version' => '1.2.0',
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
    private function handlePromptsList(mixed $id): array
    {
        $prompts = [];
        foreach ($this->prompts as $prompt) {
            $prompts[] = $prompt->toArray();
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['prompts' => $prompts]];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handlePromptsGet(mixed $id, array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!isset($this->prompts[$name])) {
            return $this->errorResponse($id, -32602, "Prompt not found: {$name}");
        }

        $prompt = $this->prompts[$name];
        $messages = $prompt->resolve($arguments);

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'description' => $prompt->description,
                'messages'    => $messages,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handlePing(mixed $id): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => []];
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
