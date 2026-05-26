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

use MonkeysLegion\Mcp\Protocol\ServerInfo;
use MonkeysLegion\Mcp\Server\McpServer as BaseServer;

/**
 * MCP Server — provides tools, resources, and prompts to MCP clients.
 *
 * @deprecated 1.3.0 Use {@see \MonkeysLegion\Mcp\Server\McpServer} from monkeyslegion-mcp instead.
 *             This class proxies all calls to the new package. Install:
 *             composer require monkeyscloud/monkeyslegion-mcp
 */
final class MCPServer
{
    public const string LATEST_PROTOCOL = '2025-03-26';

    private readonly BaseServer $delegate;

    public function __construct()
    {
        $this->delegate = new BaseServer(new ServerInfo('MonkeysLegion-Apex', '1.1.0'));
    }

    /**
     * Register a tool.
     *
     * @deprecated Use McpServer::addTool() instead.
     *
     * @param array<string, mixed> $schema JSON Schema for the tool params.
     */
    public function tool(string $name, string $description, array $schema, callable $handler): self
    {
        $this->delegate->addTool($name, $description, $schema, $handler);
        return $this;
    }

    /**
     * Register a static resource.
     *
     * @deprecated Use McpServer::addResource() instead.
     */
    public function resource(string $name, string $uri, string $content, string $mimeType = 'text/plain'): self
    {
        $this->delegate->addResource($name, $uri, $content, $mimeType);
        return $this;
    }

    /**
     * Register a prompt template.
     *
     * @deprecated Use McpServer::addPrompt() with PromptDefinition instead.
     */
    public function prompt(MCPPrompt $prompt): self
    {
        $this->delegate->addPrompt($prompt->toPromptDefinition());
        return $this;
    }

    /**
     * Process an MCP JSON-RPC request.
     *
     * @deprecated Use McpServer::handle() instead.
     *
     * @param array<string, mixed> $request
     * @param array<string, string> $headers Request headers (for version negotiation)
     * @return array<string, mixed>
     */
    public function handle(array $request, array $headers = []): array
    {
        return $this->delegate->handle($request, $headers);
    }

    /**
     * Get response headers for the Streamable HTTP transport.
     *
     * @deprecated Use McpServer::responseHeaders() instead.
     *
     * @return array<string, string>
     */
    public function responseHeaders(): array
    {
        return $this->delegate->responseHeaders();
    }

    /**
     * Get current session ID.
     *
     * @deprecated Use McpServer::sessionId() instead.
     */
    public function sessionId(): ?string
    {
        return $this->delegate->sessionId();
    }

    /**
     * Get the underlying McpServer instance for direct access.
     */
    public function delegate(): BaseServer
    {
        return $this->delegate;
    }
}
