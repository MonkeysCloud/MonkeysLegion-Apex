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
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\ModelPricing;
use MonkeysLegion\Apex\Cost\PricingRegistry;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\ToolCall;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Exception\SchemaValidationException;
use MonkeysLegion\Apex\Streaming\TextStream;
use MonkeysLegion\Apex\Testing\FakeProvider;
use MonkeysLegion\Apex\Tests\Fixture\SentimentResult;
use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;
use MonkeysLegion\Apex\Tool\ToolExecutor;
use MonkeysLegion\Apex\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

// ── Test Tool Object ──────────────────────────────────────

final class CalculatorTool
{
    /**
     * Add two numbers together.
     */
    #[Tool(name: 'add')]
    public function add(
        #[ToolParam(description: 'First number')]
        float $a,
        #[ToolParam(description: 'Second number')]
        float $b,
    ): float {
        return $a + $b;
    }

    /**
     * Multiply two numbers.
     */
    #[Tool(name: 'multiply')]
    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    #[Tool(name: 'greet')]
    public function greet(
        string $name,
        #[ToolParam(description: 'Optional greeting', enum: ['hello', 'hi', 'hey'])]
        string $greeting = 'hello',
    ): string {
        return "{$greeting}, {$name}!";
    }
}

// ═══════════════════════════════════════════════════════════════
// ██ APEX PHASE 2 — TEST SUITE █████████████████████████████████
// ═══════════════════════════════════════════════════════════════

final class ApexPhase2Test extends TestCase
{
    // ── FakeProvider ───────────────────────────────────────

    public function test_fake_provider_basic(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('Hello!')
            ->respondWith('World!');

        $r1 = $fake->chat([Message::user('hi')]);
        $this->assertSame('Hello!', $r1->content);

        $r2 = $fake->chat([Message::user('yo')]);
        $this->assertSame('World!', $r2->content);

        $this->assertSame(2, $fake->calledTimes());
    }

    public function test_fake_provider_response_object(): void
    {
        $response = new Response(
            content: 'Custom',
            finishReason: FinishReason::Stop,
            usage: new Usage(100, 50),
            model: 'custom-model',
            provider: 'test',
        );

        $fake = FakeProvider::create()->respondWith($response);
        $result = $fake->chat([]);
        $this->assertSame('Custom', $result->content);
        $this->assertSame(100, $result->usage->promptTokens);
    }

    public function test_fake_provider_fail(): void
    {
        $fake = FakeProvider::create()
            ->failWith(new ProviderException('Rate limited', 'test', 429));

        $this->expectException(ProviderException::class);
        $fake->chat([]);
    }

    public function test_fake_provider_stream(): void
    {
        $fake = FakeProvider::create()->respondWith('Hello World');
        $chunks = [];
        foreach ($fake->streamChat([Message::user('hi')]) as $chunk) {
            $chunks[] = $chunk;
        }
        $this->assertGreaterThanOrEqual(2, count($chunks)); // at least "Hello" + "World" + Done
    }

    public function test_fake_provider_embed(): void
    {
        $fake = FakeProvider::create();
        $vectors = $fake->embed(['hello', 'world']);
        $this->assertCount(2, $vectors);
        $this->assertSame('hello', $vectors[0]->input);
    }

    public function test_fake_provider_calls_tracking(): void
    {
        $fake = FakeProvider::create()->respondWith('ok');
        $fake->chat([Message::user('test')], ['temperature' => 0.5]);
        $call = $fake->lastCall();
        $this->assertSame(0.5, $call['options']['temperature']);
    }

    public function test_fake_provider_empty_response(): void
    {
        $fake = FakeProvider::create();
        $result = $fake->chat([]);
        $this->assertSame('', $result->content);
    }

    // ── ToolRegistry ──────────────────────────────────────

    public function test_tool_registry_discover(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);

        $this->assertContains('add', $registry->names());
        $this->assertContains('multiply', $registry->names());
        $this->assertContains('greet', $registry->names());
    }

    public function test_tool_registry_compile(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);
        $schemas = $registry->compile();

        $this->assertCount(3, $schemas);

        $addSchema = $schemas[0];
        $this->assertSame('function', $addSchema['type']);
        $this->assertSame('add', $addSchema['function']['name']);
        $this->assertArrayHasKey('a', $addSchema['function']['parameters']['properties']);
        $this->assertSame('number', $addSchema['function']['parameters']['properties']['a']['type']);
        $this->assertSame('First number', $addSchema['function']['parameters']['properties']['a']['description']);
    }

    public function test_tool_registry_with_optional_params(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);
        $schemas = $registry->compile();

        // find greet schema
        $greetSchema = null;
        foreach ($schemas as $s) {
            if ($s['function']['name'] === 'greet') {
                $greetSchema = $s;
                break;
            }
        }

        $this->assertNotNull($greetSchema);
        $this->assertContains('name', $greetSchema['function']['parameters']['required']);
        $this->assertNotContains('greeting', $greetSchema['function']['parameters']['required']);
        $this->assertSame(['hello', 'hi', 'hey'], $greetSchema['function']['parameters']['properties']['greeting']['enum']);
    }

    // ── ToolExecutor ──────────────────────────────────────

    public function test_tool_executor_success(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);
        $executor = new ToolExecutor($registry);

        $result = $executor->execute(new ToolCall('c1', 'add', ['a' => 3.0, 'b' => 5.0]));
        $this->assertTrue($result->success);
        $this->assertSame(8.0, $result->output);
    }

    public function test_tool_executor_with_defaults(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);
        $executor = new ToolExecutor($registry);

        $result = $executor->execute(new ToolCall('c2', 'greet', ['name' => 'Jorge']));
        $this->assertTrue($result->success);
        $this->assertSame('hello, Jorge!', $result->output);
    }

    public function test_tool_executor_unknown_tool(): void
    {
        $registry = new ToolRegistry();
        $executor = new ToolExecutor($registry);

        $result = $executor->execute(new ToolCall('c3', 'unknown', []));
        $this->assertFalse($result->success);
        $this->assertSame('Unknown tool: unknown', $result->error);
    }

    public function test_tool_executor_batch(): void
    {
        $registry = new ToolRegistry();
        $registry->register([new CalculatorTool()]);
        $executor = new ToolExecutor($registry);

        $results = $executor->executeAll([
            new ToolCall('c1', 'add', ['a' => 1.0, 'b' => 2.0]),
            new ToolCall('c2', 'multiply', ['a' => 3.0, 'b' => 4.0]),
        ]);
        $this->assertCount(2, $results);
        $this->assertSame(3.0, $results[0]->output);
        $this->assertSame(12.0, $results[1]->output);
    }

    // ── TextStream ────────────────────────────────────────

    public function test_text_stream_iterate(): void
    {
        $gen = (function(): \Generator {
            yield new StreamChunk(StreamEvent::TextDelta, 'Hello');
            yield new StreamChunk(StreamEvent::TextDelta, ' World');
            yield new StreamChunk(StreamEvent::Done, '');
        })();

        $stream = new TextStream($gen);
        $deltas = [];
        foreach ($stream as $chunk) {
            $deltas[] = $chunk->delta;
        }

        $this->assertSame('Hello', $deltas[0]);
        $this->assertSame(' World', $deltas[1]);
    }

    public function test_text_stream_text(): void
    {
        $gen = (function(): \Generator {
            yield new StreamChunk(StreamEvent::TextDelta, 'Hello');
            yield new StreamChunk(StreamEvent::TextDelta, ' World');
        })();

        $stream = new TextStream($gen);
        $this->assertSame('Hello World', $stream->text());
    }

    public function test_text_stream_sse(): void
    {
        $gen = (function(): \Generator {
            yield new StreamChunk(StreamEvent::TextDelta, 'Hi');
        })();

        $stream = new TextStream($gen);
        $sseLines = [];
        foreach ($stream->toSSE() as $line) {
            $sseLines[] = $line;
        }

        $this->assertStringStartsWith('data: ', $sseLines[0]);
        $this->assertSame("data: [DONE]\n\n", end($sseLines));
    }

    public function test_text_stream_pipe(): void
    {
        $gen = (function(): \Generator {
            yield new StreamChunk(StreamEvent::TextDelta, 'A');
            yield new StreamChunk(StreamEvent::TextDelta, 'B');
        })();

        $captured = [];
        $stream = new TextStream($gen);
        $full = $stream->pipe(function(StreamChunk $c) use (&$captured): void {
            $captured[] = $c->delta;
        });
        $this->assertSame('AB', $full);
        $this->assertSame(['A', 'B'], $captured);
    }

    // ── PricingRegistry ───────────────────────────────────

    public function test_pricing_registry_default(): void
    {
        $registry = new PricingRegistry();
        $pricing = $registry->get('claude-sonnet-4');
        $this->assertSame(3.0, $pricing->inputPerMillion);
        $this->assertSame(15.0, $pricing->outputPerMillion);
    }

    public function test_pricing_registry_alias(): void
    {
        $registry = new PricingRegistry();
        $pricing = $registry->get('sonnet');
        $this->assertSame(3.0, $pricing->inputPerMillion);
    }

    public function test_pricing_registry_unknown(): void
    {
        $registry = new PricingRegistry();
        $pricing = $registry->get('unknown-model');
        $this->assertSame(0.0, $pricing->inputPerMillion);
        $this->assertSame(0.0, $pricing->outputPerMillion);
    }

    public function test_pricing_registry_register(): void
    {
        $registry = new PricingRegistry();
        $registry->register('my-model', 5.0, 25.0);
        $pricing = $registry->get('my-model');
        $this->assertSame(5.0, $pricing->inputPerMillion);
        $this->assertSame(25.0, $pricing->outputPerMillion);
    }

    // ── CostTracker ───────────────────────────────────────

    public function test_cost_tracker(): void
    {
        $registry = new PricingRegistry();
        $tracker = new CostTracker($registry);

        $cost1 = $tracker->record('claude-sonnet-4', new Usage(1_000_000, 500_000));
        $this->assertEqualsWithDelta(3.0, $cost1->inputCost, 0.01);
        $this->assertEqualsWithDelta(7.5, $cost1->outputCost, 0.01);

        $cost2 = $tracker->record('claude-haiku-4', new Usage(100_000, 50_000));
        $this->assertEqualsWithDelta($cost1->total + $cost2->total, $tracker->totalCost(), 0.01);
    }

    public function test_cost_tracker_reset(): void
    {
        $tracker = new CostTracker(new PricingRegistry());
        $tracker->record('claude-sonnet-4', new Usage(100, 100));
        $this->assertGreaterThan(0.0, $tracker->totalCost());
        $tracker->reset();
        $this->assertSame(0.0, $tracker->totalCost());
    }

    // ── AI Facade ─────────────────────────────────────────

    public function test_ai_generate(): void
    {
        $fake = FakeProvider::create()->respondWith('Hello!');
        $ai = new AI($fake);

        $response = $ai->generate('hi', system: 'Be helpful');
        $this->assertSame('Hello!', $response->content);
        $this->assertSame(1, $fake->calledTimes());

        $call = $fake->lastCall();
        $this->assertCount(2, $call['messages']); // system + user
    }

    public function test_ai_generate_with_model(): void
    {
        $fake = FakeProvider::create()->respondWith('ok');
        $ai = new AI($fake);

        $ai->generate('test', model: 'claude-opus-4');
        $call = $fake->lastCall();
        $this->assertSame('claude-opus-4', $call['options']['model']);
    }

    public function test_ai_generate_with_cost_tracking(): void
    {
        $fake = FakeProvider::create()->respondWith('ok');
        $tracker = new CostTracker(new PricingRegistry());
        $ai = new AI($fake, $tracker);

        $ai->generate('test');
        $this->assertGreaterThanOrEqual(0.0, $tracker->totalCost());
        $this->assertCount(1, $tracker->all());
    }

    public function test_ai_stream(): void
    {
        $fake = FakeProvider::create()->respondWith('Hello World');
        $ai = new AI($fake);

        $stream = $ai->stream('hi');
        $this->assertInstanceOf(TextStream::class, $stream);
        $text = $stream->text();
        $this->assertStringContainsString('Hello', $text);
    }

    public function test_ai_extract(): void
    {
        $fake = FakeProvider::create()->respondWith(
            '{"sentiment": "positive", "confidence": 0.95}'
        );
        $ai = new AI($fake);

        $result = $ai->extract(SentimentResult::class, 'Great product!');
        $this->assertInstanceOf(SentimentResult::class, $result);
        $this->assertSame('positive', $result->sentiment);
        $this->assertSame(0.95, $result->confidence);
    }

    public function test_ai_extract_retry(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('not json')
            ->respondWith('{"sentiment": "negative", "confidence": 0.8}');
        $ai = new AI($fake);

        $result = $ai->extract(SentimentResult::class, 'Bad product');
        $this->assertSame('negative', $result->sentiment);
        $this->assertSame(2, $fake->calledTimes());
    }

    public function test_ai_extract_fails_after_retries(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('bad1')
            ->respondWith('bad2')
            ->respondWith('bad3');
        $ai = new AI($fake);

        $this->expectException(SchemaValidationException::class);
        $ai->extract(SentimentResult::class, 'test', retries: 3);
    }

    public function test_ai_embed(): void
    {
        $fake = FakeProvider::create();
        $ai = new AI($fake);

        $vectors = $ai->embed('hello');
        $this->assertCount(1, $vectors);
        $this->assertSame('hello', $vectors[0]->input);
    }

    public function test_ai_embed_multiple(): void
    {
        $fake = FakeProvider::create();
        $ai = new AI($fake);

        $vectors = $ai->embed(['hello', 'world']);
        $this->assertCount(2, $vectors);
    }

    public function test_ai_provider(): void
    {
        $fake = FakeProvider::create();
        $ai = new AI($fake);
        $this->assertSame($fake, $ai->provider());
    }

    public function test_ai_message_array_input(): void
    {
        $fake = FakeProvider::create()->respondWith('ok');
        $ai = new AI($fake);

        $messages = [
            Message::system('Be concise'),
            Message::user('What is PHP?'),
        ];

        $ai->generate($messages);
        $call = $fake->lastCall();
        $this->assertCount(2, $call['messages']);
    }

    // ── AI with Tool Calling ──────────────────────────────

    public function test_ai_generate_with_tools(): void
    {
        // First response: tool call, second response: final text
        $toolCallResponse = new Response(
            content: '',
            finishReason: FinishReason::ToolCall,
            usage: new Usage(10, 5),
            toolCalls: [new ToolCall('tc1', 'add', ['a' => 3.0, 'b' => 5.0])],
            model: 'fake-model',
            provider: 'fake',
        );

        $fake = FakeProvider::create()
            ->respondWith($toolCallResponse)
            ->respondWith('The sum is 8.0');

        $ai = new AI($fake);
        $response = $ai->generate('What is 3 + 5?', options: [
            'tools' => [new CalculatorTool()],
        ]);

        $this->assertSame('The sum is 8.0', $response->content);
        $this->assertSame(2, $fake->calledTimes());

        // Second call should include tool result
        $secondCall = $fake->getCalls()[1];
        $this->assertGreaterThan(2, count($secondCall['messages']));
    }
}
