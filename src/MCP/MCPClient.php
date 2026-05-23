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

use MonkeysLegion\Mcp\Client\ClientConfig;
use MonkeysLegion\Mcp\Client\McpClient as BaseClient;

/**
 * MCP Client — connects to external MCP servers to use their tools.
 *
 * @deprecated 1.3.0 Use {@see \MonkeysLegion\Mcp\Client\McpClient} from monkeyslegion-mcp instead.
 *             This class proxies all calls to the new package. Install:
 *             composer require monkeyscloud/monkeyslegion-mcp
 */
final class MCPClient
{
    private readonly BaseClient $delegate;

    public function __construct(
        string $serverUrl,
        float  $timeout = 30.0,
        string $requestedVersion = MCPServer::LATEST_PROTOCOL,
    ) {
        $this->delegate = new BaseClient(new ClientConfig(
            serverUrl: $serverUrl,
            timeout: (int) $timeout,
            requestedVersion: $requestedVersion,
        ));
    }

    /**
     * Initialize connection with the MCP server.
     *
     * @deprecated Use McpClient::initialize() instead.
     *
     * @return array<string, mixed>
     */
    public function initialize(): array
    {
        return $this->delegate->initialize();
    }

    /**
     * List available tools from the server.
     *
     * @deprecated Use McpClient::listTools() instead.
     *
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        return $this->delegate->listTools();
    }

    /**
     * Call a tool on the MCP server.
     *
     * @deprecated Use McpClient::callTool() instead.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->delegate->callTool($name, $arguments);
    }

    /**
     * List available resources.
     *
     * @deprecated Use McpClient::listResources() instead.
     *
     * @return list<array<string, mixed>>
     */
    public function listResources(): array
    {
        return $this->delegate->listResources();
    }

    /**
     * Read a resource by URI.
     *
     * @deprecated Use McpClient::readResource() instead.
     *
     * @return array<string, mixed>
     */
    public function readResource(string $uri): array
    {
        return $this->delegate->readResource($uri);
    }

    /**
     * List available prompts.
     *
     * @deprecated Use McpClient::listPrompts() instead.
     *
     * @return list<array<string, mixed>>
     */
    public function listPrompts(): array
    {
        return $this->delegate->listPrompts();
    }

    /**
     * Get a prompt with resolved arguments.
     *
     * @deprecated Use McpClient::getPrompt() instead.
     *
     * @param array<string, string> $arguments
     * @return array<string, mixed>
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        return $this->delegate->getPrompt($name, $arguments);
    }

    /**
     * Ping the server.
     *
     * @deprecated Use McpClient::ping() instead.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->delegate->ping();
    }

    /**
     * Get the negotiated protocol version.
     *
     * @deprecated Use McpClient::protocolVersion() instead.
     */
    public function protocolVersion(): string
    {
        return $this->delegate->protocolVersion();
    }

    /**
     * Get the current session ID.
     *
     * @deprecated Use McpClient::sessionId() instead.
     */
    public function sessionId(): ?string
    {
        return $this->delegate->sessionId();
    }

    /**
     * Get the underlying McpClient instance for direct access.
     */
    public function delegate(): BaseClient
    {
        return $this->delegate;
    }
}
