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

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Agent\Agent;
use MonkeysLegion\Apex\Agent\AgentRunner;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\Enum\GuardAction;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Event\EventDispatcher;
use MonkeysLegion\Apex\Event\RequestCompletedEvent;
use MonkeysLegion\Apex\Event\RequestFailedEvent;
use MonkeysLegion\Apex\Guard\Action\BlockAction;
use MonkeysLegion\Apex\Guard\Action\RedactAction;
use MonkeysLegion\Apex\Guard\Action\ReplaceAction;
use MonkeysLegion\Apex\Guard\Action\RetryAction;
use MonkeysLegion\Apex\Guard\Action\TruncateAction;
use MonkeysLegion\Apex\Guard\Action\WarnAction;
use MonkeysLegion\Apex\Guard\GuardPipeline;
use MonkeysLegion\Apex\Guard\Validator\PIIDetectorValidator;
use MonkeysLegion\Apex\Guard\Validator\PromptInjectionValidator;
use MonkeysLegion\Apex\Guard\Validator\WordCountValidator;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\MCP\MCPServer;
use MonkeysLegion\Apex\Pipeline\Pipeline;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\PipelineRunner;
use MonkeysLegion\Apex\Pipeline\Step\HumanInLoopStep;
use MonkeysLegion\Apex\Pipeline\Step\ParallelStep;
use MonkeysLegion\Apex\Provider\Google\GoogleProvider;
use MonkeysLegion\Apex\Streaming\SSEStream;
use MonkeysLegion\Apex\Streaming\StreamBuffer;
use MonkeysLegion\Apex\Testing\FakeProvider;
use MonkeysLegion\Apex\Tool\ToolSchemaCompiler;
use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;
use PHPUnit\Framework\TestCase;

/**
 * Phase 5 — extended tests for Google, Guard Actions, Events, MCP, Streaming, Tools.
 */
final class ApexPhase5Test extends TestCase
{
    // ── Google Provider ───────────────────────────────────

    public function test_google_provider_name(): void
    {
        $provider = new GoogleProvider(apiKey: 'test-key', model: 'gemini-2.5-flash');
        $this->assertSame('google', $provider->name());
    }

    public function test_google_provider_model_info(): void
    {
        $provider = new GoogleProvider(apiKey: 'test-key');
        $info = $provider->modelInfo('gemini-2.5-flash');
        $this->assertSame('google', $info->provider);
        $this->assertSame(1_000_000, $info->contextWindow);
    }

    public function test_google_provider_list_models(): void
    {
        $provider = new GoogleProvider(apiKey: 'test-key');
        $models = $provider->listModels();
        $this->assertGreaterThanOrEqual(2, count($models));
    }

    public function test_google_vertex_mode(): void
    {
        $provider = new GoogleProvider(
            apiKey: 'test-key',
            project: 'my-project',
            location: 'us-central1',
        );
        $this->assertSame('vertex', $provider->name());
    }

    // ── Guard Actions ─────────────────────────────────────

    public function test_block_action_throws(): void
    {
        $result = new GuardResult(passed: false, text: 'bad text', violations: ['pii' => true], validator: 'test');
        $this->expectException(\MonkeysLegion\Apex\Exception\GuardException::class);
        (new BlockAction())->execute($result);
    }

    public function test_redact_action(): void
    {
        $result = new GuardResult(passed: false, text: 'original', redactedText: '[REDACTED]', violations: [], validator: 'test');
        $output = (new RedactAction())->execute($result);
        $this->assertSame('[REDACTED]', $output->text);
    }

    public function test_replace_action(): void
    {
        $result = new GuardResult(passed: false, text: 'bad', violations: [], validator: 'test');
        $output = (new ReplaceAction('REPLACED'))->execute($result);
        $this->assertSame('REPLACED', $output->text);
    }

    public function test_warn_action(): void
    {
        $result = new GuardResult(passed: false, text: 'warn text', violations: ['x' => 1], validator: 'test');
        $output = (new WarnAction())->execute($result);
        $this->assertSame('warn text', $output->text);
    }

    public function test_truncate_action(): void
    {
        $result = new GuardResult(passed: false, text: 'abcdef', violations: [], validator: 'test', maxLength: 3);
        $output = (new TruncateAction())->execute($result);
        $this->assertSame('abc', $output->text);
    }

    public function test_retry_action(): void
    {
        $result = new GuardResult(passed: false, text: 'retry me', violations: [], validator: 'test');
        $output = (new RetryAction())->execute($result);
        $this->assertArrayHasKey('_retry', $output->violations);
    }

    // ── Guard Pipeline ────────────────────────────────────

    public function test_guard_pipeline_all_pass(): void
    {
        $pipeline = GuardPipeline::create()
            ->add(new WordCountValidator(minWords: 1, maxWords: 100));

        $results = $pipeline->validate('Hello world');
        $this->assertTrue($results[0]->passed);
        $this->assertTrue($pipeline->passes('Hello world'));
    }

    public function test_guard_pipeline_redact_action(): void
    {
        $pipeline = GuardPipeline::create()
            ->add(new PIIDetectorValidator(), GuardAction::Redact);

        $results = $pipeline->validate('Email me at test@example.com');
        $this->assertFalse($results[0]->passed);
    }

    // ── Event Dispatcher ──────────────────────────────────

    public function test_event_dispatcher(): void
    {
        $dispatcher = new EventDispatcher();
        $captured = null;

        $dispatcher->listen('ai.request.completed', function ($event) use (&$captured) {
            $captured = $event;
        });

        $response = new \MonkeysLegion\Apex\DTO\Response(
            content: 'test', finishReason: \MonkeysLegion\Apex\Enum\FinishReason::Stop,
            usage: new \MonkeysLegion\Apex\DTO\Usage(100, 50),
        );
        $event = new RequestCompletedEvent($response, 50.0, 'test-model');
        $dispatcher->dispatch($event);

        $this->assertNotNull($captured);
        $this->assertSame('ai.request.completed', $captured->name());
    }

    public function test_event_dispatcher_wildcard(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;

        $dispatcher->listen('*', function () use (&$count) { $count++; });

        $response = new \MonkeysLegion\Apex\DTO\Response(
            content: 'test', finishReason: \MonkeysLegion\Apex\Enum\FinishReason::Stop,
            usage: new \MonkeysLegion\Apex\DTO\Usage(100, 50),
        );
        $dispatcher->dispatch(new RequestCompletedEvent($response, 10.0, 'test'));
        $dispatcher->dispatch(new RequestFailedEvent(new \RuntimeException('err'), 'test'));

        $this->assertSame(2, $count);
    }

    public function test_event_has_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $this->assertFalse($dispatcher->hasListeners('ai.request.completed'));
        $dispatcher->listen('ai.request.completed', fn() => null);
        $this->assertTrue($dispatcher->hasListeners('ai.request.completed'));
    }

    // ── MCP Server ────────────────────────────────────────

    public function test_mcp_server_initialize(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'initialize', 'id' => 1]);
        $this->assertSame('2.0', $result['jsonrpc']);
        $this->assertSame('MonkeysLegion-Apex', $result['result']['serverInfo']['name']);
    }

    public function test_mcp_server_tools(): void
    {
        $server = new MCPServer();
        $server->tool('greet', 'Says hello', ['type' => 'object', 'properties' => []], fn() => 'Hello!');

        $list = $server->handle(['method' => 'tools/list', 'id' => 2]);
        $this->assertCount(1, $list['result']['tools']);
        $this->assertSame('greet', $list['result']['tools'][0]['name']);

        $call = $server->handle(['method' => 'tools/call', 'id' => 3, 'params' => ['name' => 'greet']]);
        $this->assertSame('Hello!', $call['result']['content'][0]['text']);
        $this->assertFalse($call['result']['isError']);
    }

    public function test_mcp_server_tool_error(): void
    {
        $server = new MCPServer();
        $server->tool('fail', 'Fails', [], fn() => throw new \RuntimeException('boom'));

        $result = $server->handle(['method' => 'tools/call', 'id' => 4, 'params' => ['name' => 'fail']]);
        $this->assertTrue($result['result']['isError']);
    }

    public function test_mcp_server_unknown_tool(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'tools/call', 'id' => 5, 'params' => ['name' => 'missing']]);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_mcp_server_resources(): void
    {
        $server = new MCPServer();
        $server->resource('readme', 'file:///README.md', '# Title', 'text/markdown');

        $list = $server->handle(['method' => 'resources/list', 'id' => 6]);
        $this->assertCount(1, $list['result']['resources']);

        $read = $server->handle(['method' => 'resources/read', 'id' => 7, 'params' => ['uri' => 'file:///README.md']]);
        $this->assertSame('# Title', $read['result']['contents'][0]['text']);
    }

    public function test_mcp_server_unknown_method(): void
    {
        $server = new MCPServer();
        $result = $server->handle(['method' => 'invalid/method', 'id' => 8]);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame(-32601, $result['error']['code']);
    }

    // ── Pipeline Steps ────────────────────────────────────

    public function test_parallel_step(): void
    {
        $result = Pipeline::create()
            ->pipe(new ParallelStep(
                fn(PipelineContext $ctx) => strtoupper($ctx->input),
                fn(PipelineContext $ctx) => strlen($ctx->input),
            ))
            ->run('test');

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->output);
        $this->assertSame('TEST', $result->output[0]);
        $this->assertSame(4, $result->output[1]);
    }

    public function test_human_in_loop_auto_approve(): void
    {
        $result = Pipeline::create()
            ->pipe(fn(PipelineContext $ctx) => 'generated text')
            ->pipe(new HumanInLoopStep())
            ->run('test');

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['human_approved']);
    }

    public function test_human_in_loop_with_reviewer(): void
    {
        $result = Pipeline::create()
            ->pipe(fn(PipelineContext $ctx) => 'draft content')
            ->pipe(new HumanInLoopStep(
                reviewer: fn(PipelineContext $ctx) => 'edited: ' . $ctx->get('last_output'),
            ))
            ->run('test');

        $this->assertSame('edited: draft content', $result->output);
        $this->assertTrue($result->data['human_approved']);
    }

    // ── Pipeline Runner ───────────────────────────────────

    public function test_pipeline_runner(): void
    {
        $runner = new PipelineRunner();
        $runner->register('upper', Pipeline::create()->pipe(fn(PipelineContext $ctx) => strtoupper($ctx->input)));

        $result = $runner->run('upper', 'hello');
        $this->assertTrue($result->success);
        $this->assertSame('HELLO', $result->output);
    }

    public function test_pipeline_runner_not_found(): void
    {
        $runner = new PipelineRunner();
        $result = $runner->run('missing', 'test');
        $this->assertFalse($result->success);
    }

    public function test_pipeline_runner_chain(): void
    {
        $runner = new PipelineRunner();
        $runner->register('upper', Pipeline::create()->pipe(fn(PipelineContext $ctx) => strtoupper($ctx->input)));
        $runner->register('exclaim', Pipeline::create()->pipe(fn(PipelineContext $ctx) => $ctx->input . '!'));

        $result = $runner->chain(['upper', 'exclaim'], 'hello');
        $this->assertTrue($result->success);
        $this->assertSame('HELLO!', $result->output);
    }

    public function test_pipeline_runner_list(): void
    {
        $runner = new PipelineRunner();
        $runner->register('a', Pipeline::create());
        $runner->register('b', Pipeline::create());
        $this->assertSame(['a', 'b'], $runner->list());
    }

    // ── Agent Runner ──────────────────────────────────────

    public function test_agent_runner(): void
    {
        $fake = FakeProvider::create()->respondWith('Agent output');
        $ai = new AI($fake);

        $runner = new AgentRunner();
        $steps = [];
        $runner->onStep(function (string $name, $response) use (&$steps) {
            $steps[] = $name;
        });

        $agent = new Agent('test-agent', 'Role', $ai);
        $response = $runner->runAgent($agent, 'Do something');

        $this->assertSame('Agent output', $response->content);
        $this->assertCount(1, $steps);
        $this->assertSame('test-agent', $steps[0]);
    }

    // ── Streaming Components ──────────────────────────────

    public function test_stream_buffer(): void
    {
        $buffer = new StreamBuffer(maxChunks: 3);
        $buffer->append(new StreamChunk(event: StreamEvent::TextDelta, delta: 'a'));
        $buffer->append(new StreamChunk(event: StreamEvent::TextDelta, delta: 'b'));
        $buffer->append(new StreamChunk(event: StreamEvent::TextDelta, delta: 'c'));
        $buffer->append(new StreamChunk(event: StreamEvent::TextDelta, delta: 'd'));

        $this->assertSame(3, $buffer->count());
        $this->assertSame('abcd', $buffer->text());
    }

    public function test_stream_buffer_flush(): void
    {
        $buffer = new StreamBuffer();
        $buffer->append(new StreamChunk(event: StreamEvent::TextDelta, delta: 'test'));
        $buffer->flush();
        $this->assertSame(0, $buffer->count());
        $this->assertSame('', $buffer->text());
    }

    public function test_sse_stream_parse(): void
    {
        $lines = [
            'event: message',
            'data: {"text": "Hello"}',
            '',
            'data: [DONE]',
            '',
        ];

        $chunks = iterator_to_array(SSEStream::parse($lines));
        $this->assertCount(2, $chunks);
        $this->assertSame(StreamEvent::TextDelta, $chunks[0]->event);
        $this->assertSame(StreamEvent::Done, $chunks[1]->event);
    }

    // ── Tool Schema Compiler ──────────────────────────────

    public function test_tool_schema_compiler(): void
    {
        $compiler = new ToolSchemaCompiler();
        $schemas = $compiler->compile([new class {
            #[Tool(name: 'greet', description: 'Say hello')]
            public function greet(
                #[ToolParam(description: 'Person name')]
                string $name,
            ): string {
                return "Hello, {$name}!";
            }
        }]);

        $this->assertCount(1, $schemas);
        $this->assertSame('greet', $schemas[0]['name']);
        $this->assertArrayHasKey('name', $schemas[0]['parameters']['properties']);
        $this->assertContains('name', $schemas[0]['parameters']['required']);
    }

    public function test_tool_schema_compiler_openai_format(): void
    {
        $compiler = new ToolSchemaCompiler();
        $schemas = $compiler->compileForOpenAI([new class {
            #[Tool(name: 'test', description: 'Test tool')]
            public function test(string $input): string { return $input; }
        }]);

        $this->assertSame('function', $schemas[0]['type']);
        $this->assertArrayHasKey('function', $schemas[0]);
    }

    public function test_tool_schema_compiler_anthropic_format(): void
    {
        $compiler = new ToolSchemaCompiler();
        $schemas = $compiler->compileForAnthropic([new class {
            #[Tool(name: 'test', description: 'Test')]
            public function test(string $x): string { return $x; }
        }]);

        $this->assertArrayHasKey('input_schema', $schemas[0]);
    }
}
