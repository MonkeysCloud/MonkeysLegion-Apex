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

namespace MonkeysLegion\Apex\Provider\Cohere;

use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\ToolCall;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Provider\AbstractProvider;

/**
 * Cohere provider — Command R+ and Embed models via native Cohere API.
 *
 * Uses Cohere's v2 Chat API (not OpenAI-compatible) with native
 * tool use, citations, and multi-lingual RAG support.
 */
final class CohereProvider extends AbstractProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.cohere.com';

    public function __construct(
        string  $apiKey,
        string  $model = 'command-r-plus',
        ?string $baseUrl = null,
        ?float  $timeout = null,
        ?int    $maxRetries = null,
        ?string $embeddingModel = null,
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model,
            baseUrl: $baseUrl,
            timeout: $timeout,
            maxRetries: $maxRetries,
            embeddingModel: $embeddingModel,
        );
    }

    public function name(): string
    {
        return 'cohere';
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $body = $this->buildBody($messages, $options);
        $start = hrtime(true);
        $raw = $this->request('POST', '/v2/chat', $body);
        $latency = (hrtime(true) - $start) / 1e6;

        return $this->mapResponse($raw, $latency);
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return \Generator<StreamChunk>
     */
    public function streamChat(array $messages, array $options = []): \Generator
    {
        $body = $this->buildBody($messages, $options);
        $body['stream'] = true;

        foreach ($this->streamRequest('POST', '/v2/chat', $body) as $line) {
            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            $eventType = $data['type'] ?? '';

            if ($eventType === 'content-delta') {
                yield new StreamChunk(
                    event: StreamEvent::TextDelta,
                    delta: $data['delta']['message']['content']['text'] ?? '',
                );
            }

            if ($eventType === 'message-end') {
                $usage = isset($data['delta']['usage']) ? new Usage(
                    $data['delta']['usage']['tokens']['input_tokens'] ?? 0,
                    $data['delta']['usage']['tokens']['output_tokens'] ?? 0,
                ) : null;

                yield new StreamChunk(
                    event: StreamEvent::Done,
                    finishReason: $data['delta']['finish_reason'] ?? 'COMPLETE',
                    usage: $usage,
                );
            }
        }
    }

    /**
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array
    {
        $model = $this->embeddingModel ?? ($this->model === 'command-r-plus' ? 'embed-v4.0' : $this->model);
        $raw = $this->request('POST', '/v2/embed', [
            'model' => $model,
            'texts' => $inputs,
            'input_type' => 'search_document',
            'embedding_types' => ['float'],
        ]);

        $vectors = [];
        $embeddings = $raw['embeddings']['float'] ?? [];
        foreach ($embeddings as $i => $embedding) {
            $vectors[] = new EmbeddingVector(
                input:      $inputs[$i] ?? '',
                vector:     $embedding,
                dimensions: count($embedding),
                model:      $raw['model'] ?? $this->model,
            );
        }

        return $vectors;
    }

    public function modelInfo(string $model): ModelInfo
    {
        $catalog = $this->buildModelCatalog();
        return $catalog[$model] ?? new ModelInfo(
            name: $model,
            provider: 'cohere',
            tier: ModelTier::Balanced,
            contextWindow: 128_000,
            maxOutputTokens: 4_096,
            inputPricePerMillion: 0.0,
            outputPricePerMillion: 0.0,
        );
    }

    /**
     * @return list<ModelInfo>
     */
    public function listModels(): array
    {
        return array_values($this->buildModelCatalog());
    }

    /**
     * @return array<string, ModelInfo>
     */
    private function buildModelCatalog(): array
    {
        return [
            'command-r-plus' => new ModelInfo(
                name: 'command-r-plus',
                provider: 'cohere',
                tier: ModelTier::Power,
                contextWindow: 128_000,
                maxOutputTokens: 4_096,
                inputPricePerMillion: 2.50,
                outputPricePerMillion: 10.00,
            ),
            'command-r' => new ModelInfo(
                name: 'command-r',
                provider: 'cohere',
                tier: ModelTier::Balanced,
                contextWindow: 128_000,
                maxOutputTokens: 4_096,
                inputPricePerMillion: 0.15,
                outputPricePerMillion: 0.60,
            ),
            'command-a' => new ModelInfo(
                name: 'command-a',
                provider: 'cohere',
                tier: ModelTier::Power,
                contextWindow: 256_000,
                maxOutputTokens: 8_192,
                inputPricePerMillion: 2.50,
                outputPricePerMillion: 10.00,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'X-Client-Name: monkeyslegion-apex',
        ];
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildBody(array $messages, array $options): array
    {
        $body = [
            'model'    => $options['model'] ?? $this->model,
            'messages' => $this->mapMessages($messages),
        ];

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = $options['max_tokens'];
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }
        if (isset($options['tools'])) {
            $body['tools'] = $options['tools'];
        }

        return $body;
    }

    /**
     * @param list<Message> $messages
     * @return list<array<string, mixed>>
     */
    protected function mapMessages(array $messages): array
    {
        $mapped = [];
        foreach ($messages as $msg) {
            $role = match ($msg->role->value) {
                'system'    => 'system',
                'assistant' => 'assistant',
                'tool'      => 'tool',
                default     => 'user',
            };

            $entry = [
                'role'    => $role,
                'content' => $msg->content,
            ];

            if ($msg->toolCalls !== null) {
                $entry['tool_calls'] = array_map(fn(ToolCall $tc) => [
                    'id'       => $tc->id,
                    'type'     => 'function',
                    'function' => [
                        'name'      => $tc->name,
                        'arguments' => json_encode($tc->arguments),
                    ],
                ], $msg->toolCalls);
            }

            if ($msg->toolCallId !== null) {
                $entry['tool_call_id'] = $msg->toolCallId;
            }

            $mapped[] = $entry;
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $raw
     */
    protected function mapResponse(array $raw, float $latencyMs = 0.0): Response
    {
        $message = $raw['message'] ?? [];
        $content = '';
        foreach ($message['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'] ?? '';
            }
        }

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = new ToolCall(
                id: $tc['id'] ?? '',
                name: $tc['function']['name'] ?? '',
                arguments: json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
            );
        }

        $usageData = $raw['usage'] ?? [];
        $usage = new Usage(
            $usageData['tokens']['input_tokens'] ?? 0,
            $usageData['tokens']['output_tokens'] ?? 0,
        );

        $finishReason = match ($raw['finish_reason'] ?? 'COMPLETE') {
            'COMPLETE'    => FinishReason::Stop,
            'TOOL_CALL'   => FinishReason::ToolCall,
            'MAX_TOKENS'  => FinishReason::Length,
            default       => FinishReason::Stop,
        };

        return new Response(
            content:      $content,
            finishReason: $finishReason,
            usage:        $usage,
            toolCalls:    empty($toolCalls) ? null : $toolCalls,
            model:        $raw['model'] ?? $this->model,
            provider:     $this->name(),
            latencyMs:    $latencyMs,
        );
    }
}
