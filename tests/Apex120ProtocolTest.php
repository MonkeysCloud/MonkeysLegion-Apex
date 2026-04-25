<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\A2A\A2AClient;
use MonkeysLegion\Apex\A2A\A2AMessage;
use MonkeysLegion\Apex\A2A\A2AServer;
use MonkeysLegion\Apex\A2A\A2ATask;
use MonkeysLegion\Apex\A2A\AgentCard;
use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Agent\Agent;
use MonkeysLegion\Apex\MCP\MCPPrompt;
use MonkeysLegion\Apex\MCP\MCPServer;
use MonkeysLegion\Apex\Testing\FakeProvider;
use PHPUnit\Framework\TestCase;

final class Apex120ProtocolTest extends TestCase
{
    // ── AgentCard ─────────────────────────────────────────

    public function test_agent_card_to_array(): void
    {
        $card = new AgentCard('bot', 'A bot', 'https://x.com', skills: ['chat']);
        $arr = $card->toArray();
        $this->assertSame('bot', $arr['name']);
        $this->assertSame(['chat'], $arr['skills']);
        $this->assertSame(['a2a/1.2'], $arr['protocols']);
    }

    public function test_agent_card_to_json(): void
    {
        $card = new AgentCard('bot', 'desc', 'https://x.com');
        $json = $card->toJson();
        $decoded = json_decode($json, true);
        $this->assertSame('bot', $decoded['name']);
    }

    public function test_agent_card_with_auth(): void
    {
        $card = new AgentCard('b', 'd', 'u', authentication: ['type' => 'bearer']);
        $arr = $card->toArray();
        $this->assertSame('bearer', $arr['authentication']['type']);
    }

    public function test_agent_card_without_auth_omits_key(): void
    {
        $card = new AgentCard('b', 'd', 'u');
        $this->assertArrayNotHasKey('authentication', $card->toArray());
    }

    // ── A2ATask ──────────────────────────────────────────

    public function test_task_create(): void
    {
        $task = A2ATask::create('do stuff');
        $this->assertSame('submitted', $task->status);
        $this->assertSame('do stuff', $task->input);
        $this->assertNotEmpty($task->id);
    }

    public function test_task_lifecycle(): void
    {
        $task = A2ATask::create('x');
        $this->assertFalse($task->isTerminal());

        $task->working();
        $this->assertSame('working', $task->status);
        $this->assertFalse($task->isTerminal());

        $task->complete('done');
        $this->assertSame('completed', $task->status);
        $this->assertSame('done', $task->output);
        $this->assertTrue($task->isTerminal());
    }

    public function test_task_fail(): void
    {
        $task = A2ATask::create('x');
        $task->fail('oops');
        $this->assertSame('failed', $task->status);
        $this->assertSame('oops', $task->error);
        $this->assertTrue($task->isTerminal());
    }

    public function test_task_cancel(): void
    {
        $task = A2ATask::create('x');
        $task->cancel();
        $this->assertSame('canceled', $task->status);
        $this->assertTrue($task->isTerminal());
    }

    public function test_task_input_required(): void
    {
        $task = A2ATask::create('x');
        $task->inputRequired();
        $this->assertSame('input-required', $task->status);
        $this->assertFalse($task->isTerminal());
    }

    public function test_task_to_array(): void
    {
        $task = A2ATask::create('x', ['key' => 'val']);
        $arr = $task->toArray();
        $this->assertSame('x', $arr['input']);
        $this->assertSame(['key' => 'val'], $arr['metadata']);
    }

    // ── A2AMessage ───────────────────────────────────────

    public function test_message_from(): void
    {
        $msg = A2AMessage::from('hello', 'task-1');
        $this->assertSame('user', $msg->role);
        $this->assertSame('task-1', $msg->taskId);
        $this->assertSame('hello', $msg->parts[0]['text']);
    }

    public function test_message_response(): void
    {
        $msg = A2AMessage::response('ok');
        $this->assertSame('agent', $msg->role);
    }

    public function test_message_to_array(): void
    {
        $msg = A2AMessage::from('hi');
        $arr = $msg->toArray();
        $this->assertSame('user', $arr['role']);
        $this->assertCount(1, $arr['parts']);
    }

    // ── A2AServer ────────────────────────────────────────

    public function test_a2a_server_discover(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $agent = new Agent('bot', 'helper', $ai);

        $server = new A2AServer();
        $server->register($agent);

        $result = $server->handle(['method' => 'agent/discover', 'id' => 1]);
        $this->assertCount(1, $result['result']['agents']);
        $this->assertSame('bot', $result['result']['agents'][0]['name']);
    }

    public function test_a2a_server_task_send(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('done'));
        $agent = new Agent('bot', 'helper', $ai);

        $server = new A2AServer();
        $server->register($agent);

        $result = $server->handle([
            'method' => 'tasks/send', 'id' => 2,
            'params' => ['agent' => 'bot', 'message' => A2AMessage::from('do it')->toArray()],
        ]);

        $this->assertSame('completed', $result['result']['status']);
        $this->assertSame('done', $result['result']['output']);
    }

    public function test_a2a_server_unknown_agent(): void
    {
        $server = new A2AServer();
        $result = $server->handle([
            'method' => 'tasks/send', 'id' => 3,
            'params' => ['agent' => 'missing', 'input' => 'x'],
        ]);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_a2a_server_task_get(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $agent = new Agent('bot', 'r', $ai);
        $server = new A2AServer();
        $server->register($agent);

        $send = $server->handle([
            'method' => 'tasks/send', 'id' => 1,
            'params' => ['agent' => 'bot', 'input' => 'x'],
        ]);
        $taskId = $send['result']['id'];

        $get = $server->handle(['method' => 'tasks/get', 'id' => 2, 'params' => ['id' => $taskId]]);
        $this->assertSame('completed', $get['result']['status']);
    }

    public function test_a2a_server_task_cancel(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $agent = new Agent('bot', 'r', $ai);
        $server = new A2AServer();
        $server->register($agent);

        $send = $server->handle([
            'method' => 'tasks/send', 'id' => 1,
            'params' => ['agent' => 'bot', 'input' => 'x'],
        ]);
        $cancel = $server->handle([
            'method' => 'tasks/cancel', 'id' => 2,
            'params' => ['id' => $send['result']['id']],
        ]);
        $this->assertSame('canceled', $cancel['result']['status']);
    }

    public function test_a2a_server_unknown_method(): void
    {
        $server = new A2AServer();
        $result = $server->handle(['method' => 'bad', 'id' => 1]);
        $this->assertSame(-32601, $result['error']['code']);
    }

    public function test_a2a_server_agent_cards(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $server = new A2AServer();
        $server->register(new Agent('a', 'r', $ai));
        $this->assertCount(1, $server->agentCards());
        $this->assertInstanceOf(AgentCard::class, $server->agentCards()[0]);
    }

    // ── MCPPrompt ────────────────────────────────────────

    public function test_mcp_prompt_resolve(): void
    {
        $prompt = new MCPPrompt('greet', 'Greet user', [
            'name' => ['description' => 'User name', 'required' => true],
        ], [
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello {name}!']],
        ]);

        $resolved = $prompt->resolve(['name' => 'Jorge']);
        $this->assertSame('Hello Jorge!', $resolved[0]['content']['text']);
    }

    public function test_mcp_prompt_to_array(): void
    {
        $prompt = new MCPPrompt('p', 'desc', ['x' => ['description' => 'd']]);
        $arr = $prompt->toArray();
        $this->assertSame('p', $arr['name']);
        $this->assertSame('x', $arr['arguments'][0]['name']);
    }

    // ── MCP Server Modernized ────────────────────────────

    public function test_mcp_server_prompts_list(): void
    {
        $server = new MCPServer();
        $server->prompt(new MCPPrompt('p1', 'desc'));

        $result = $server->handle(['method' => 'prompts/list', 'id' => 1]);
        $this->assertCount(1, $result['result']['prompts']);
    }

    public function test_mcp_server_prompts_get(): void
    {
        $server = new MCPServer();
        $server->prompt(new MCPPrompt('greet', 'Greet', ['name' => ['description' => 'n']], [
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hi {name}']],
        ]));

        $result = $server->handle([
            'method' => 'prompts/get', 'id' => 1,
            'params' => ['name' => 'greet', 'arguments' => ['name' => 'X']],
        ]);
        $this->assertSame('Hi X', $result['result']['messages'][0]['content']['text']);
    }

    public function test_mcp_server_prompts_get_not_found(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'prompts/get', 'id' => 1, 'params' => ['name' => 'x']]);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_mcp_server_ping(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'ping', 'id' => 1]);
        $this->assertSame([], $result['result']);
    }

    public function test_mcp_server_session_management(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'initialize', 'id' => 1]);
        $this->assertNotNull($server->sessionId());
    }

    public function test_mcp_server_version_negotiation(): void
    {
        $server = new MCPServer();
        $result = $server->handle(
            ['method' => 'initialize', 'id' => 1],
            ['MCP-Protocol-Version' => '2024-11-05'],
        );
        $this->assertSame('2024-11-05', $result['result']['protocolVersion']);
    }

    public function test_mcp_server_latest_version(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'initialize', 'id' => 1]);
        $this->assertSame('2025-11-25', $result['result']['protocolVersion']);
    }

    public function test_mcp_server_response_headers(): void
    {
        $server = new MCPServer();
        $server->handle(['method' => 'initialize', 'id' => 1]);
        $headers = $server->responseHeaders();
        $this->assertArrayHasKey('MCP-Session-Id', $headers);
        $this->assertArrayHasKey('MCP-Protocol-Version', $headers);
    }

    public function test_mcp_server_prompts_capability_excluded_for_legacy(): void
    {
        $server = new MCPServer();
        $result = $server->handle(
            ['method' => 'initialize', 'id' => 1],
            ['MCP-Protocol-Version' => '2024-11-05'],
        );
        $this->assertArrayNotHasKey('prompts', $result['result']['capabilities']);
    }

    public function test_mcp_server_prompts_capability_included_for_modern(): void
    {
        $server = new MCPServer();
        $result = $server->handle(
            ['method' => 'initialize', 'id' => 1],
            ['MCP-Protocol-Version' => '2025-11-25'],
        );
        $this->assertArrayHasKey('prompts', $result['result']['capabilities']);
    }
}
