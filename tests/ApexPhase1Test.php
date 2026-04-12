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

use MonkeysLegion\Apex\DTO\Cost;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\ToolCall;
use MonkeysLegion\Apex\DTO\ToolResult;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\AgentProcess;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\GuardAction;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\PipelineProcess;
use MonkeysLegion\Apex\Enum\Role;
use MonkeysLegion\Apex\Enum\RouterStrategy;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Exception\AIException;
use MonkeysLegion\Apex\Exception\BudgetExceededException;
use MonkeysLegion\Apex\Exception\GuardException;
use MonkeysLegion\Apex\Exception\PipelineException;
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Exception\RateLimitException;
use MonkeysLegion\Apex\Exception\SchemaValidationException;
use MonkeysLegion\Apex\Exception\TimeoutException;
use MonkeysLegion\Apex\Exception\ToolExecutionException;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;
use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\SchemaCompiler;
use MonkeysLegion\Apex\Schema\SchemaValidator;
use MonkeysLegion\Apex\Tests\Fixture\Invoice;
use MonkeysLegion\Apex\Tests\Fixture\LineItem;
use MonkeysLegion\Apex\Tests\Fixture\SentimentResult;
use MonkeysLegion\Apex\Tests\Fixture\TagList;
use PHPUnit\Framework\TestCase;

// ═══════════════════════════════════════════════════════════════
// ██ APEX PHASE 1 — TEST SUITE █████████████████████████████████
// ═══════════════════════════════════════════════════════════════

final class ApexPhase1Test extends TestCase
{
    // ── Enums ───────────────────────────────────────────────

    public function test_role_enum(): void
    {
        $this->assertSame('system', Role::System->value);
        $this->assertSame('user', Role::User->value);
        $this->assertSame('assistant', Role::Assistant->value);
        $this->assertSame('tool', Role::Tool->value);
        $this->assertCount(4, Role::cases());
    }

    public function test_finish_reason_enum(): void
    {
        $this->assertSame('stop', FinishReason::Stop->value);
        $this->assertSame('tool_call', FinishReason::ToolCall->value);
        $this->assertSame('content_filter', FinishReason::ContentFilter->value);
        $this->assertCount(4, FinishReason::cases());
    }

    public function test_model_tier_enum(): void
    {
        $this->assertSame('fast', ModelTier::Fast->value);
        $this->assertSame('balanced', ModelTier::Balanced->value);
        $this->assertSame('power', ModelTier::Power->value);
        $this->assertCount(3, ModelTier::cases());
    }

    public function test_router_strategy_enum(): void
    {
        $this->assertSame('cost_optimized', RouterStrategy::CostOptimized->value);
        $this->assertSame('round_robin', RouterStrategy::RoundRobin->value);
        $this->assertCount(4, RouterStrategy::cases());
    }

    public function test_guard_action_enum(): void
    {
        $this->assertSame('block', GuardAction::Block->value);
        $this->assertSame('redact', GuardAction::Redact->value);
        $this->assertCount(7, GuardAction::cases());
    }

    public function test_pipeline_process_enum(): void
    {
        $this->assertCount(3, PipelineProcess::cases());
        $this->assertSame('sequential', PipelineProcess::Sequential->value);
    }

    public function test_agent_process_enum(): void
    {
        $this->assertCount(4, AgentProcess::cases());
        $this->assertSame('hierarchical', AgentProcess::Hierarchical->value);
    }

    public function test_stream_event_enum(): void
    {
        $this->assertSame('text_delta', StreamEvent::TextDelta->value);
        $this->assertSame('done', StreamEvent::Done->value);
        $this->assertCount(5, StreamEvent::cases());
    }

    // ── DTOs: Message ──────────────────────────────────────

    public function test_message_system(): void
    {
        $msg = Message::system('You are helpful.');
        $this->assertSame(Role::System, $msg->role);
        $this->assertSame('You are helpful.', $msg->content);
    }

    public function test_message_user(): void
    {
        $msg = Message::user('Hello', [['type' => 'image', 'data' => 'base64...']]);
        $this->assertSame(Role::User, $msg->role);
        $this->assertNotNull($msg->attachments);
    }

    public function test_message_assistant(): void
    {
        $tc = [new ToolCall('id-1', 'search', ['q' => 'php'])];
        $msg = Message::assistant('I found:', $tc);
        $this->assertSame(Role::Assistant, $msg->role);
        $this->assertCount(1, $msg->toolCalls);
    }

    public function test_message_tool(): void
    {
        $msg = Message::tool('{"result":"ok"}', 'call-1');
        $this->assertSame(Role::Tool, $msg->role);
        $this->assertSame('call-1', $msg->toolCallId);
    }

    public function test_message_to_array(): void
    {
        $msg = Message::user('hi');
        $arr = $msg->toArray();
        $this->assertSame('user', $arr['role']);
        $this->assertSame('hi', $arr['content']);
        $this->assertArrayNotHasKey('tool_calls', $arr);
    }

    // ── DTOs: Usage ────────────────────────────────────────

    public function test_usage(): void
    {
        $u = new Usage(100, 200);
        $this->assertSame(300, $u->totalTokens);
        $arr = $u->toArray();
        $this->assertSame(100, $arr['prompt_tokens']);
    }

    // ── DTOs: Cost ─────────────────────────────────────────

    public function test_cost(): void
    {
        $c = new Cost(0.001, 0.002, 'claude-sonnet-4');
        $this->assertEqualsWithDelta(0.003, $c->total, 0.0001);
        $arr = $c->toArray();
        $this->assertSame('claude-sonnet-4', $arr['model']);
    }

    // ── DTOs: Response ─────────────────────────────────────

    public function test_response(): void
    {
        $r = new Response(
            content: 'Hello!',
            finishReason: FinishReason::Stop,
            usage: new Usage(10, 5),
            model: 'claude-sonnet-4',
            provider: 'anthropic',
            latencyMs: 123.4,
        );
        $this->assertSame('Hello!', $r->content);
        $this->assertFalse($r->hasToolCalls());
        $this->assertSame(15, $r->usage->totalTokens);
    }

    public function test_response_with_tools(): void
    {
        $r = new Response(
            content: '',
            finishReason: FinishReason::ToolCall,
            usage: new Usage(50, 10),
            toolCalls: [new ToolCall('c1', 'search', ['q' => 'php'])],
        );
        $this->assertTrue($r->hasToolCalls());
    }

    // ── DTOs: StreamChunk ──────────────────────────────────

    public function test_stream_chunk(): void
    {
        $c = new StreamChunk(StreamEvent::TextDelta, 'Hello');
        $this->assertSame('Hello', $c->delta);
        $arr = $c->toArray();
        $this->assertSame('text_delta', $arr['event']);
    }

    // ── DTOs: ToolCall & ToolResult ────────────────────────

    public function test_tool_call(): void
    {
        $tc = new ToolCall('id-1', 'search', ['q' => 'test']);
        $this->assertSame('search', $tc->name);
        $arr = $tc->toArray();
        $this->assertSame('id-1', $arr['id']);
    }

    public function test_tool_result(): void
    {
        $tr = new ToolResult('id-1', ['data' => 123], true);
        $this->assertTrue($tr->success);
        $this->assertNull($tr->error);
    }

    public function test_tool_result_failure(): void
    {
        $tr = new ToolResult('id-1', null, false, 'Tool not found');
        $this->assertFalse($tr->success);
        $this->assertSame('Tool not found', $tr->error);
    }

    // ── DTOs: GuardResult ──────────────────────────────────

    public function test_guard_result_passed(): void
    {
        $r = new GuardResult(passed: true, text: 'clean text');
        $this->assertTrue($r->passed);
        $this->assertEmpty($r->violations);
    }

    public function test_guard_result_failed(): void
    {
        $r = new GuardResult(
            passed: false,
            text: 'SSN: 123-45-6789',
            redactedText: 'SSN: [REDACTED]',
            violations: ['ssn' => ['123-45-6789']],
            validator: 'pii_detector',
        );
        $this->assertFalse($r->passed);
        $this->assertSame('SSN: [REDACTED]', $r->redactedText);
    }

    // ── DTOs: EmbeddingVector ──────────────────────────────

    public function test_embedding_vector(): void
    {
        $v = new EmbeddingVector('hello', [0.1, 0.2, 0.3], 3, 'text-embedding-3-small');
        $this->assertSame('hello', $v->input);
        $this->assertCount(3, $v->vector);
    }

    public function test_embedding_cosine_similarity_identical(): void
    {
        $a = new EmbeddingVector('a', [1.0, 0.0, 0.0], 3);
        $b = new EmbeddingVector('b', [1.0, 0.0, 0.0], 3);
        $this->assertEqualsWithDelta(1.0, $a->cosineSimilarity($b), 0.0001);
    }

    public function test_embedding_cosine_similarity_orthogonal(): void
    {
        $a = new EmbeddingVector('a', [1.0, 0.0], 2);
        $b = new EmbeddingVector('b', [0.0, 1.0], 2);
        $this->assertEqualsWithDelta(0.0, $a->cosineSimilarity($b), 0.0001);
    }

    // ── DTOs: ModelInfo ────────────────────────────────────

    public function test_model_info(): void
    {
        $m = new ModelInfo(
            name: 'claude-sonnet-4',
            provider: 'anthropic',
            tier: ModelTier::Balanced,
            contextWindow: 200000,
            maxOutputTokens: 64000,
            inputPricePerMillion: 3.0,
            outputPricePerMillion: 15.0,
        );
        $this->assertSame('anthropic', $m->provider);
        $arr = $m->toArray();
        $this->assertSame('balanced', $arr['tier']);
    }

    // ── Exceptions ─────────────────────────────────────────

    public function test_exception_hierarchy(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new AIException());
        $this->assertInstanceOf(AIException::class, new ProviderException());
        $this->assertInstanceOf(AIException::class, new SchemaValidationException());
        $this->assertInstanceOf(AIException::class, new GuardException(
            new GuardResult(passed: false, text: ''),
        ));
        $this->assertInstanceOf(AIException::class, new BudgetExceededException(100.0, 105.0));
        $this->assertInstanceOf(AIException::class, new RateLimitException());
        $this->assertInstanceOf(AIException::class, new ToolExecutionException('tool'));
        $this->assertInstanceOf(AIException::class, new PipelineException('step'));
        $this->assertInstanceOf(AIException::class, new TimeoutException(30.0));
    }

    public function test_exception_status_codes(): void
    {
        $this->assertSame(500, (new AIException())->getStatusCode());
        $this->assertSame(502, (new ProviderException())->getStatusCode());
        $this->assertSame(422, (new SchemaValidationException())->getStatusCode());
        $this->assertSame(429, (new BudgetExceededException(0, 0))->getStatusCode());
        $this->assertSame(429, (new RateLimitException())->getStatusCode());
        $this->assertSame(504, (new TimeoutException(30.0))->getStatusCode());
    }

    public function test_exception_context(): void
    {
        $e = new AIException('test', ['key' => 'value']);
        $this->assertSame(['key' => 'value'], $e->context);
    }

    public function test_provider_exception_data(): void
    {
        $e = new ProviderException('Rate limited', 'anthropic', 429);
        $this->assertSame('anthropic', $e->providerName);
        $this->assertSame(429, $e->httpStatus);
    }

    public function test_budget_exception_data(): void
    {
        $e = new BudgetExceededException(100.0, 105.0);
        $this->assertSame(100.0, $e->budget);
        $this->assertSame(105.0, $e->spent);
    }

    public function test_rate_limit_exception_retry(): void
    {
        $e = new RateLimitException(30);
        $this->assertSame(30, $e->retryAfter);
    }

    public function test_timeout_exception(): void
    {
        $e = new TimeoutException(60.0);
        $this->assertSame(60.0, $e->timeoutSeconds);
    }

    public function test_tool_execution_exception(): void
    {
        $e = new ToolExecutionException('calculator');
        $this->assertSame('calculator', $e->toolName);
    }

    public function test_pipeline_exception(): void
    {
        $e = new PipelineException('classify');
        $this->assertSame('classify', $e->stepName);
    }

    public function test_schema_validation_exception(): void
    {
        $e = new SchemaValidationException('Bad data', ['Field x required']);
        $this->assertSame(['Field x required'], $e->errors);
        $this->assertSame(422, $e->getStatusCode());
    }

    // ── MiddlewareContext ──────────────────────────────────

    public function test_middleware_context(): void
    {
        $ctx = new MiddlewareContext(
            [Message::user('hello')],
            'claude-sonnet-4',
            ['temperature' => 0.7],
        );
        $this->assertCount(1, $ctx->messages);
        $this->assertSame('claude-sonnet-4', $ctx->model);
        $this->assertNull($ctx->response);
        $this->assertGreaterThan(0, $ctx->elapsedMs());
    }

    // ── Schema: Constrain Attribute ────────────────────────

    public function test_constrain_to_schema(): void
    {
        $c = new Constrain(min: 0, max: 100, minLength: 1, maxLength: 50);
        $schema = $c->toSchema();
        $this->assertSame(0.0, $schema['minimum']);
        $this->assertSame(100.0, $schema['maximum']);
        $this->assertSame(1, $schema['minLength']);
        $this->assertSame(50, $schema['maxLength']);
    }

    public function test_constrain_empty(): void
    {
        $c = new Constrain();
        $this->assertSame([], $c->toSchema());
    }

    // ── SchemaCompiler ─────────────────────────────────────

    public function test_compile_sentiment_schema(): void
    {
        $schema = SchemaCompiler::compile(SentimentResult::class);

        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertArrayHasKey('sentiment', $schema['properties']);
        $this->assertArrayHasKey('confidence', $schema['properties']);
        $this->assertArrayHasKey('explanation', $schema['properties']);

        // sentiment should have enum constraint
        $this->assertSame(['positive', 'negative', 'neutral'], $schema['properties']['sentiment']['enum']);

        // confidence should have min/max
        $this->assertSame(0.0, $schema['properties']['confidence']['minimum']);
        $this->assertSame(1.0, $schema['properties']['confidence']['maximum']);

        // explanation is optional — not in required
        $this->assertContains('sentiment', $schema['required']);
        $this->assertContains('confidence', $schema['required']);
        $this->assertNotContains('explanation', $schema['required']);
    }

    public function test_compile_nested_schema(): void
    {
        $schema = SchemaCompiler::compile(Invoice::class);

        $this->assertSame('object', $schema['type']);
        $this->assertContains('vendor', $schema['required']);
        $this->assertContains('items', $schema['required']);
        $this->assertContains('total', $schema['required']);
        $this->assertNotContains('poNumber', $schema['required']);

        // items should be array of LineItem objects
        $itemsProp = $schema['properties']['items'];
        $this->assertSame('array', $itemsProp['type']);
        $this->assertSame('object', $itemsProp['items']['type']);
        $this->assertArrayHasKey('description', $itemsProp['items']['properties']);
    }

    public function test_compile_string_array_schema(): void
    {
        $schema = SchemaCompiler::compile(TagList::class);
        $this->assertSame('array', $schema['properties']['tags']['type']);
        $this->assertSame('string', $schema['properties']['tags']['items']['type']);
    }

    public function test_compile_description_attribute(): void
    {
        $schema = SchemaCompiler::compile(SentimentResult::class);
        $this->assertSame('The detected sentiment', $schema['properties']['sentiment']['description']);
    }

    public function test_compile_example_attribute(): void
    {
        $schema = SchemaCompiler::compile(LineItem::class);
        $this->assertSame([9.99, 29.99], $schema['properties']['unitPrice']['examples']);
    }

    // ── SchemaValidator ────────────────────────────────────

    public function test_validate_sentiment(): void
    {
        $result = SchemaValidator::validate(SentimentResult::class, [
            'sentiment'  => 'positive',
            'confidence' => 0.95,
        ]);
        $this->assertInstanceOf(SentimentResult::class, $result);
        $this->assertSame('positive', $result->sentiment);
        $this->assertSame(0.95, $result->confidence);
        $this->assertNull($result->explanation);
    }

    public function test_validate_sentiment_with_optional(): void
    {
        $result = SchemaValidator::validate(SentimentResult::class, [
            'sentiment'   => 'negative',
            'confidence'  => 0.8,
            'explanation' => 'Bad review',
        ]);
        $this->assertSame('Bad review', $result->explanation);
    }

    public function test_validate_missing_required_field(): void
    {
        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('Missing required field: sentiment');
        SchemaValidator::validate(SentimentResult::class, [
            'confidence' => 0.5,
        ]);
    }

    public function test_validate_wrong_type(): void
    {
        $this->expectException(SchemaValidationException::class);
        SchemaValidator::validate(SentimentResult::class, [
            'sentiment'  => 123,
            'confidence' => 0.5,
        ]);
    }

    public function test_validate_int_coerced_to_float(): void
    {
        $result = SchemaValidator::validate(SentimentResult::class, [
            'sentiment'  => 'neutral',
            'confidence' => 1,
        ]);
        $this->assertSame(1.0, $result->confidence);
    }

    public function test_validate_nested_schema(): void
    {
        $result = SchemaValidator::validate(Invoice::class, [
            'vendor' => 'Acme Corp',
            'items'  => [
                ['description' => 'Widget', 'quantity' => 3, 'unitPrice' => 9.99],
            ],
            'total'  => 29.97,
        ]);
        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertSame('Acme Corp', $result->vendor);
        $this->assertSame(29.97, $result->total);
    }

    // ── Schema: toArray / jsonSerialize ────────────────────

    public function test_schema_to_array(): void
    {
        $result = new SentimentResult('positive', 0.95, 'Great!');
        $arr = $result->toArray();
        $this->assertSame('positive', $arr['sentiment']);
        $this->assertSame(0.95, $arr['confidence']);
        $this->assertSame('Great!', $arr['explanation']);
    }

    public function test_schema_json_serialize(): void
    {
        $result = new SentimentResult('neutral', 0.5);
        $json = json_encode($result);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('neutral', $decoded['sentiment']);
    }

    public function test_schema_from_array(): void
    {
        $result = SentimentResult::fromArray([
            'sentiment'  => 'positive',
            'confidence' => 0.9,
        ]);
        $this->assertSame('positive', $result->sentiment);
    }

    public function test_schema_to_json_schema_static(): void
    {
        $schema = SentimentResult::toJsonSchema();
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
    }
}
