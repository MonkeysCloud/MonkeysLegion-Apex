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
 */
final class MCPClient
{
    /** @var array<string, array<string, mixed>> */
    private array $tools = [];

    public function __construct(
        private readonly string $serverUrl,
        private readonly float  $timeout = 30.0,
    ) {}

    /**
     * Initialize connection with the MCP server.
     *
     * @return array<string, mixed>
     */
    public function initialize(): array
    {
        return $this->send('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => [],
            'clientInfo'      => [
                'name'    => 'MonkeysLegion-Apex',
                'version' => '2.0.0',
            ],
        ]);
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
     * Send a JSON-RPC request to the MCP server.
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

        $ch = curl_init($this->serverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
