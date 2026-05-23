<?php
declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\MCP\MCPClient;
use MonkeysLegion\Apex\MCP\MCPPrompt;
use MonkeysLegion\Apex\MCP\MCPServer;

use MonkeysLegion\Mcp\Client\McpClient as BaseClient;
use MonkeysLegion\Mcp\Prompt\PromptDefinition;
use MonkeysLegion\Mcp\Server\McpServer as BaseServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the deprecated Apex MCP bridge classes.
 *
 * Verifies that the old API surface (MCPServer, MCPClient, MCPPrompt)
 * correctly delegates to monkeyslegion-mcp.
 */
#[CoversClass(MCPServer::class)]
#[CoversClass(MCPClient::class)]
#[CoversClass(MCPPrompt::class)]
final class McpBridgeTest extends TestCase
{
    // ── MCPServer ────────────────────────────────────────────

    public function testServerProtocolConstant(): void
    {
        $this->assertSame('2025-03-26', MCPServer::LATEST_PROTOCOL);
    }

    public function testServerToolRegistration(): void
    {
        $server = new MCPServer();
        $result = $server->tool('add', 'Add two numbers', [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']],
            'required' => ['a', 'b'],
        ], fn(array $args) => $args['a'] + $args['b']);

        // Fluent return
        $this->assertSame($server, $result);
    }

    public function testServerResourceRegistration(): void
    {
        $server = new MCPServer();
        $result = $server->resource('readme', 'file:///README.md', '# Hello', 'text/markdown');

        $this->assertSame($server, $result);
    }

    public function testServerPromptRegistration(): void
    {
        $server = new MCPServer();
        $prompt = new MCPPrompt(
            name: 'greeting',
            description: 'Say hello',
            arguments: ['name' => ['description' => 'Person name', 'required' => true]],
            messages: [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello {name}!']]],
        );

        $result = $server->prompt($prompt);
        $this->assertSame($server, $result);
    }

    public function testServerHandlePing(): void
    {
        $server   = new MCPServer();
        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(1, $response['id']);
        $this->assertSame([], $response['result']);
    }

    public function testServerHandleInitialize(): void
    {
        $server   = new MCPServer();
        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => '2025-03-26',
                'capabilities'    => [],
                'clientInfo'      => ['name' => 'Test', 'version' => '1.0.0'],
            ],
        ]);

        $this->assertSame('2025-03-26', $response['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertArrayHasKey('resources', $response['result']['capabilities']);
        $this->assertArrayHasKey('prompts', $response['result']['capabilities']);
    }

    public function testServerSessionId(): void
    {
        $server = new MCPServer();

        // Before initialize, session is null
        $this->assertNull($server->sessionId());

        $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => '2025-03-26',
                'capabilities'    => [],
                'clientInfo'      => ['name' => 'T', 'version' => '1.0'],
            ],
        ]);

        // After initialize, session is generated
        $this->assertNotNull($server->sessionId());
        $this->assertSame(32, strlen($server->sessionId()));
    }

    public function testServerResponseHeaders(): void
    {
        $server = new MCPServer();
        $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => '2025-03-26',
                'capabilities'    => [],
                'clientInfo'      => ['name' => 'T', 'version' => '1.0'],
            ],
        ]);

        $headers = $server->responseHeaders();
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertArrayHasKey('MCP-Protocol-Version', $headers);
        $this->assertArrayHasKey('MCP-Session-Id', $headers);
    }

    public function testServerToolsList(): void
    {
        $server = new MCPServer();
        $server->tool('calc', 'Calculate', ['type' => 'object', 'properties' => []], fn($a) => 42);

        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        $this->assertCount(1, $response['result']['tools']);
        $this->assertSame('calc', $response['result']['tools'][0]['name']);
    }

    public function testServerToolsCall(): void
    {
        $server = new MCPServer();
        $server->tool('add', 'Add', [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']],
            'required' => ['a', 'b'],
        ], fn(array $args) => $args['a'] + $args['b']);

        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'add', 'arguments' => ['a' => 10, 'b' => 20]],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertSame('30', $response['result']['content'][0]['text']);
    }

    public function testServerResourcesList(): void
    {
        $server = new MCPServer();
        $server->resource('readme', 'file:///README.md', '# Hi', 'text/markdown');

        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/list']);

        $this->assertCount(1, $response['result']['resources']);
        $this->assertSame('readme', $response['result']['resources'][0]['name']);
    }

    public function testServerResourcesRead(): void
    {
        $server = new MCPServer();
        $server->resource('readme', 'file:///README.md', '# Hi', 'text/markdown');

        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 5,
            'method'  => 'resources/read',
            'params'  => ['uri' => 'file:///README.md'],
        ]);

        $this->assertSame('# Hi', $response['result']['contents'][0]['text']);
    }

    public function testServerPromptsList(): void
    {
        $server = new MCPServer();
        $server->prompt(new MCPPrompt('greet', 'Greet someone'));

        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'prompts/list']);

        $this->assertCount(1, $response['result']['prompts']);
        $this->assertSame('greet', $response['result']['prompts'][0]['name']);
    }

    public function testServerPromptsGet(): void
    {
        $server = new MCPServer();
        $server->prompt(new MCPPrompt(
            'greet', 'Greet',
            ['name' => ['description' => 'Name', 'required' => true]],
            [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hi {name}!']]],
        ));

        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id'      => 7,
            'method'  => 'prompts/get',
            'params'  => ['name' => 'greet', 'arguments' => ['name' => 'Alice']],
        ]);

        $this->assertSame('Hi Alice!', $response['result']['messages'][0]['content']['text']);
    }

    public function testServerUnknownMethod(): void
    {
        $server   = new MCPServer();
        $response = $server->handle(['jsonrpc' => '2.0', 'id' => 8, 'method' => 'unknown']);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    public function testServerDelegate(): void
    {
        $server = new MCPServer();
        $this->assertInstanceOf(BaseServer::class, $server->delegate());
    }

    // ── MCPPrompt ────────────────────────────────────────────

    public function testPromptProperties(): void
    {
        $prompt = new MCPPrompt('test', 'Test prompt');

        $this->assertSame('test', $prompt->name);
        $this->assertSame('Test prompt', $prompt->description);
        $this->assertSame([], $prompt->arguments);
        $this->assertSame([], $prompt->messages);
    }

    public function testPromptResolve(): void
    {
        $prompt = new MCPPrompt(
            'greet', 'Greet',
            ['name' => ['description' => 'Name']],
            [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello {name}!']]],
        );

        $resolved = $prompt->resolve(['name' => 'World']);
        $this->assertSame('Hello World!', $resolved[0]['content']['text']);
    }

    public function testPromptResolveEmptyArgs(): void
    {
        $prompt = new MCPPrompt(
            'greet', 'Greet', [],
            [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello {name}!']]],
        );

        $resolved = $prompt->resolve();
        $this->assertSame('Hello {name}!', $resolved[0]['content']['text']);
    }

    public function testPromptToArray(): void
    {
        $prompt = new MCPPrompt(
            'code', 'Code review',
            ['lang' => ['description' => 'Language', 'required' => true]],
        );

        $array = $prompt->toArray();
        $this->assertSame('code', $array['name']);
        $this->assertSame('Code review', $array['description']);
        $this->assertCount(1, $array['arguments']);
        $this->assertSame('lang', $array['arguments'][0]['name']);
        $this->assertTrue($array['arguments'][0]['required']);
    }

    public function testPromptToPromptDefinition(): void
    {
        $prompt = new MCPPrompt('test', 'Test prompt');
        $def    = $prompt->toPromptDefinition();

        $this->assertInstanceOf(PromptDefinition::class, $def);
        $this->assertSame('test', $def->name);
        $this->assertSame('Test prompt', $def->description);
    }

    // ── MCPClient ────────────────────────────────────────────

    public function testClientConstruction(): void
    {
        // Just verify it constructs without error
        $client = new MCPClient('http://localhost:8080/mcp');
        $this->assertInstanceOf(MCPClient::class, $client);
    }

    public function testClientWithTimeout(): void
    {
        $client = new MCPClient('http://localhost:8080/mcp', timeout: 60.0);
        $this->assertInstanceOf(MCPClient::class, $client);
    }

    public function testClientWithVersion(): void
    {
        $client = new MCPClient(
            'http://localhost:8080/mcp',
            timeout: 30.0,
            requestedVersion: '2024-11-05',
        );
        $this->assertInstanceOf(MCPClient::class, $client);
    }

    public function testClientDelegate(): void
    {
        $client = new MCPClient('http://localhost:8080/mcp');
        $this->assertInstanceOf(BaseClient::class, $client->delegate());
    }
}
