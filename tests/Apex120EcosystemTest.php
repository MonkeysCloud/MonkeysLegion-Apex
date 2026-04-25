<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\Embedding\InMemoryStore;
use MonkeysLegion\Apex\Embedding\VectorStoreInterface;
use MonkeysLegion\Apex\Provider\DeepSeek\DeepSeekProvider;
use MonkeysLegion\Apex\Provider\Groq\GroqProvider;
use MonkeysLegion\Apex\Provider\Mistral\MistralProvider;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;
use MonkeysLegion\Apex\RAG\DocumentSplitter;
use MonkeysLegion\Apex\RAG\FixedSizeChunker;
use MonkeysLegion\Apex\RAG\RAGResult;
use MonkeysLegion\Apex\RAG\RecursiveChunker;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\PricingRegistry;
use MonkeysLegion\Apex\Router\ModelRegistry;
use PHPUnit\Framework\TestCase;

final class Apex120EcosystemTest extends TestCase
{
    // ── VectorStoreInterface ─────────────────────────────

    public function test_in_memory_store_implements_interface(): void
    {
        $store = new InMemoryStore();
        $this->assertInstanceOf(VectorStoreInterface::class, $store);
    }

    public function test_in_memory_store_add_and_count(): void
    {
        $store = new InMemoryStore();
        $this->assertSame(0, $store->count());
        $store->add(new EmbeddingVector('hi', [0.1, 0.2], 2, 'model'));
        $this->assertSame(1, $store->count());
    }

    public function test_in_memory_store_search(): void
    {
        $store = new InMemoryStore();
        $store->add(new EmbeddingVector('hello', [1.0, 0.0, 0.0], 3, 'm'), ['tag' => 'greet']);
        $store->add(new EmbeddingVector('bye', [0.0, 1.0, 0.0], 3, 'm'));

        $query = new EmbeddingVector('q', [1.0, 0.0, 0.0], 3, 'm');
        $results = $store->search($query, 1);
        $this->assertCount(1, $results);
        $this->assertSame('hello', $results[0]['vector']->input);
        $this->assertSame('greet', $results[0]['metadata']['tag']);
    }

    public function test_in_memory_store_delete(): void
    {
        $store = new InMemoryStore();
        $store->add(new EmbeddingVector('a', [0.1], 1, 'm'));
        $store->add(new EmbeddingVector('b', [0.2], 1, 'm'));

        $this->assertTrue($store->delete('a'));
        $this->assertSame(1, $store->count());
        $this->assertFalse($store->delete('nonexistent'));
    }

    public function test_in_memory_store_clear(): void
    {
        $store = new InMemoryStore();
        $store->add(new EmbeddingVector('x', [0.1], 1, 'm'));
        $store->clear();
        $this->assertSame(0, $store->count());
    }

    // ── FixedSizeChunker ─────────────────────────────────

    public function test_fixed_size_chunker(): void
    {
        $chunker = new FixedSizeChunker(chunkSize: 10, overlap: 0);
        $chunks = $chunker->chunk('abcdefghijklmnopqrst'); // 20 chars
        $this->assertCount(2, $chunks);
        $this->assertSame('abcdefghij', $chunks[0]);
    }

    public function test_fixed_size_chunker_with_overlap(): void
    {
        $chunker = new FixedSizeChunker(chunkSize: 10, overlap: 5);
        $chunks = $chunker->chunk('abcdefghijklmnopqrst');
        $this->assertGreaterThanOrEqual(3, count($chunks));
    }

    public function test_fixed_size_chunker_small_input(): void
    {
        $chunker = new FixedSizeChunker(chunkSize: 100, overlap: 0);
        $chunks = $chunker->chunk('short');
        $this->assertCount(1, $chunks);
        $this->assertSame('short', $chunks[0]);
    }

    public function test_fixed_size_chunker_empty(): void
    {
        $chunker = new FixedSizeChunker();
        $this->assertSame([], $chunker->chunk(''));
    }

    // ── RecursiveChunker ─────────────────────────────────

    public function test_recursive_chunker_paragraphs(): void
    {
        $chunker = new RecursiveChunker(maxChunkSize: 20);
        $text = "First paragraph here.\n\nSecond paragraph here.";
        $chunks = $chunker->chunk($text);
        $this->assertGreaterThanOrEqual(2, count($chunks));
    }

    public function test_recursive_chunker_small_input(): void
    {
        $chunker = new RecursiveChunker(maxChunkSize: 100);
        $chunks = $chunker->chunk('short text');
        $this->assertCount(1, $chunks);
    }

    public function test_recursive_chunker_empty(): void
    {
        $chunker = new RecursiveChunker();
        $this->assertSame([], $chunker->chunk(''));
    }

    // ── DocumentSplitter ─────────────────────────────────

    public function test_document_splitter_with_metadata(): void
    {
        $splitter = new DocumentSplitter(new FixedSizeChunker(chunkSize: 10, overlap: 0));
        $result = $splitter->split('abcdefghijklmnop', ['source' => 'test']);

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertSame('test', $result[0]['metadata']['source']);
        $this->assertSame(0, $result[0]['metadata']['chunk_index']);
        $this->assertArrayHasKey('chunk_total', $result[0]['metadata']);
        $this->assertArrayHasKey('char_count', $result[0]['metadata']);
    }

    // ── RAGResult ────────────────────────────────────────

    public function test_rag_result_no_context(): void
    {
        $response = new \MonkeysLegion\Apex\DTO\Response(
            'answer', \MonkeysLegion\Apex\Enum\FinishReason::Stop,
            new \MonkeysLegion\Apex\DTO\Usage(10, 5),
        );
        $result = new RAGResult($response, [], 'question');
        $this->assertSame('answer', $result->content());
        $this->assertSame(0, $result->contextCount());
        $this->assertSame(0.0, $result->bestScore());
        $this->assertFalse($result->hasContext());
    }

    public function test_rag_result_with_context(): void
    {
        $response = new \MonkeysLegion\Apex\DTO\Response(
            'ans', \MonkeysLegion\Apex\Enum\FinishReason::Stop,
            new \MonkeysLegion\Apex\DTO\Usage(10, 5),
        );
        $vec = new EmbeddingVector('ctx', [0.1], 1, 'm');
        $result = new RAGResult($response, [
            ['vector' => $vec, 'metadata' => [], 'score' => 0.95],
        ], 'q');
        $this->assertTrue($result->hasContext());
        $this->assertSame(1, $result->contextCount());
        $this->assertSame(0.95, $result->bestScore());
    }

    // ── Providers ────────────────────────────────────────

    public function test_generic_provider_name(): void
    {
        $p = new GenericProvider(providerName: 'custom');
        $this->assertSame('custom', $p->name());
    }

    public function test_generic_provider_default_name(): void
    {
        $p = new GenericProvider();
        $this->assertSame('openai-compatible', $p->name());
    }

    public function test_generic_provider_model_info(): void
    {
        $p = new GenericProvider(providerName: 'my');
        $info = $p->modelInfo('test-model');
        $this->assertSame('my', $info->provider);
        $this->assertSame('test-model', $info->name);
    }

    public function test_deepseek_provider_name(): void
    {
        $p = new DeepSeekProvider(apiKey: 'key');
        $this->assertSame('deepseek', $p->name());
    }

    public function test_deepseek_provider_model_catalog(): void
    {
        $p = new DeepSeekProvider(apiKey: 'key');
        $models = $p->listModels();
        $this->assertCount(2, $models);
        $names = array_map(fn($m) => $m->name, $models);
        $this->assertContains('deepseek-chat', $names);
        $this->assertContains('deepseek-reasoner', $names);
    }

    public function test_deepseek_provider_model_info(): void
    {
        $p = new DeepSeekProvider(apiKey: 'key');
        $info = $p->modelInfo('deepseek-chat');
        $this->assertSame('deepseek', $info->provider);
    }

    public function test_mistral_provider_name(): void
    {
        $p = new MistralProvider(apiKey: 'key');
        $this->assertSame('mistral', $p->name());
    }

    public function test_mistral_provider_model_catalog(): void
    {
        $p = new MistralProvider(apiKey: 'key');
        $models = $p->listModels();
        $this->assertCount(4, $models);
        $names = array_map(fn($m) => $m->name, $models);
        $this->assertContains('mistral-large-latest', $names);
        $this->assertContains('codestral-latest', $names);
    }

    public function test_groq_provider_name(): void
    {
        $p = new GroqProvider(apiKey: 'key');
        $this->assertSame('groq', $p->name());
    }

    public function test_groq_provider_model_catalog(): void
    {
        $p = new GroqProvider(apiKey: 'key');
        $models = $p->listModels();
        $this->assertCount(4, $models);
        $names = array_map(fn($m) => $m->name, $models);
        $this->assertContains('llama-3.3-70b-versatile', $names);
        $this->assertContains('gemma2-9b-it', $names);
    }

    public function test_groq_provider_model_info(): void
    {
        $p = new GroqProvider(apiKey: 'key');
        $info = $p->modelInfo('llama-3.3-70b-versatile');
        $this->assertSame('groq', $info->provider);
    }

    // ── Registry Updates ─────────────────────────────────

    public function test_model_registry_has_deepseek(): void
    {
        $reg = new ModelRegistry();
        $this->assertNotNull($reg->get('deepseek-chat'));
        $this->assertNotNull($reg->get('deepseek-reasoner'));
    }

    public function test_model_registry_has_mistral(): void
    {
        $reg = new ModelRegistry();
        $this->assertNotNull($reg->get('mistral-large-latest'));
        $this->assertNotNull($reg->get('codestral-latest'));
    }

    public function test_model_registry_has_groq(): void
    {
        $reg = new ModelRegistry();
        $this->assertNotNull($reg->get('llama-3.3-70b-versatile'));
        $this->assertNotNull($reg->get('gemma2-9b-it'));
    }

    public function test_model_registry_by_provider(): void
    {
        $reg = new ModelRegistry();
        $this->assertGreaterThanOrEqual(2, count($reg->byProvider('deepseek')));
        $this->assertGreaterThanOrEqual(4, count($reg->byProvider('mistral')));
        $this->assertGreaterThanOrEqual(4, count($reg->byProvider('groq')));
    }

    public function test_pricing_registry_has_new_models(): void
    {
        $reg = new PricingRegistry();
        $p = $reg->get('deepseek-chat');
        $this->assertGreaterThan(0, $p->inputPerMillion);

        $p2 = $reg->get('mistral-large-latest');
        $this->assertGreaterThan(0, $p2->inputPerMillion);

        $p3 = $reg->get('llama-3.3-70b-versatile');
        $this->assertGreaterThan(0, $p3->inputPerMillion);
    }

    // ── CostTracker report() ─────────────────────────────

    public function test_cost_tracker_report(): void
    {
        $tracker = new CostTracker(new PricingRegistry());
        $tracker->record('gpt-4.1', new \MonkeysLegion\Apex\DTO\Usage(1000, 500));
        $report = $tracker->report();
        $this->assertInstanceOf(\MonkeysLegion\Apex\Cost\CostReport::class, $report);
        $this->assertGreaterThan(0, $report->summary['total']);
    }
}
