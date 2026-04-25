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
 * MCP Client — connects to external MCP servers to use their tools.
 *
 * Supports MCP 2025-11-25 Streamable HTTP transport:
 *   - Protocol version negotiation via MCP-Protocol-Version header
 *   - Session management via MCP-Session-Id header
 *   - Prompts primitive
 */
final class MCPClient
{
    /** @var array<string, array<string, mixed>> */
    private array $tools = [];

    /** @var ?string Server-provided session ID */
    private ?string $sessionId = null;

    /** @var string Negotiated protocol version */
    private string $protocolVersion = MCPServer::LATEST_PROTOCOL;

    public function __construct(
        private readonly string $serverUrl,
        private readonly float  $timeout = 30.0,
        private readonly string $requestedVersion = MCPServer::LATEST_PROTOCOL,
    ) {}

    /**
     * Initialize connection with the MCP server.
     *
     * @return array<string, mixed>
     */
    public function initialize(): array
    {
        $result = $this->send('initialize', [
            'protocolVersion' => $this->requestedVersion,
            'capabilities'    => [],
            'clientInfo'      => [
                'name'    => 'MonkeysLegion-Apex',
                'version' => '1.2.0',
            ],
        ]);

        // Capture negotiated version
        if (isset($result['protocolVersion'])) {
            $this->protocolVersion = $result['protocolVersion'];
        }

        return $result;
    }

    /**
     * List available tools from the server.
     *
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        $result = $this->send('tools/list');
        $this->tools = [];

        foreach ($result['tools'] ?? [] as $tool) {
            $this->tools[$tool['name']] = $tool;
        }

        return $result['tools'] ?? [];
    }

    /**
     * Call a tool on the MCP server.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->send('tools/call', [
            'name'      => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * List available resources.
     *
     * @return list<array<string, mixed>>
     */
    public function listResources(): array
    {
        $result = $this->send('resources/list');
        return $result['resources'] ?? [];
    }

    /**
     * Read a resource by URI.
     *
     * @return array<string, mixed>
     */
    public function readResource(string $uri): array
    {
        return $this->send('resources/read', ['uri' => $uri]);
    }

    /**
     * List available prompts (MCP 2025-03-26+).
     *
     * @return list<array<string, mixed>>
     */
    public function listPrompts(): array
    {
        $result = $this->send('prompts/list');
        return $result['prompts'] ?? [];
    }

    /**
     * Get a prompt with resolved arguments (MCP 2025-03-26+).
     *
     * @param array<string, string> $arguments
     * @return array<string, mixed>
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        return $this->send('prompts/get', [
            'name'      => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Ping the server to check connectivity.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->send('ping');
    }

    /**
     * Get the negotiated protocol version.
     */
    public function protocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Get the current session ID.
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Send a JSON-RPC request to the MCP server.
     *
     * Uses Streamable HTTP transport (single endpoint POST).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function send(string $method, array $params = []): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id'      => bin2hex(random_bytes(8)),
            'method'  => $method,
            'params'  => (object) $params,
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'MCP-Protocol-Version: ' . $this->requestedVersion,
        ];

        // Include session ID if established
        if ($this->sessionId !== null) {
            $headers[] = 'MCP-Session-Id: ' . $this->sessionId;
        }

        $ch = curl_init($this->serverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($ch, string $header): int {
                // Capture session ID from response headers
                if (stripos($header, 'MCP-Session-Id:') === 0) {
                    $this->sessionId = trim(substr($header, 15));
                }
                return strlen($header);
            },
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            throw new \RuntimeException("MCP request failed: {$err} (HTTP {$code})");
        }

        $response = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        if (isset($response['error'])) {
            throw new \RuntimeException("MCP error: {$response['error']['message']}");
        }

        return $response['result'] ?? [];
    }
}
