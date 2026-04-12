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

namespace MonkeysLegion\Apex\Provider\OpenAI;

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
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Provider\AbstractProvider;

/**
 * OpenAI Chat Completions and Embeddings provider.
 */
final class OpenAIProvider extends AbstractProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.openai.com';

    public function name(): string
    {
        return 'openai';
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $body = $this->buildBody($messages, $options);
        $start = hrtime(true);
        $raw = $this->request('POST', '/v1/chat/completions', $body);
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

        foreach ($this->streamRequest('POST', '/v1/chat/completions', $body) as $line) {
            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            $choice = $data['choices'][0] ?? [];
            $delta = $choice['delta'] ?? [];

            if (isset($delta['content'])) {
                yield new StreamChunk(
                    event: StreamEvent::TextDelta,
                    delta: $delta['content'],
                );
            }

            if (isset($choice['finish_reason'])) {
                yield new StreamChunk(
                    event: StreamEvent::Done,
                    finishReason: $choice['finish_reason'],
                    usage: isset($data['usage']) ? new Usage(
                        $data['usage']['prompt_tokens'] ?? 0,
                        $data['usage']['completion_tokens'] ?? 0,
                    ) : null,
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
        $raw = $this->request('POST', '/v1/embeddings', [
            'model' => 'text-embedding-3-small',
            'input' => $inputs,
        ]);

        $vectors = [];
        foreach ($raw['data'] ?? [] as $i => $entry) {
            $vectors[] = new EmbeddingVector(
                input:      $inputs[$i] ?? '',
                vector:     $entry['embedding'],
                dimensions: count($entry['embedding']),
                model:      $raw['model'] ?? 'text-embedding-3-small',
            );
        }

        return $vectors;
    }

    public function modelInfo(string $model): ModelInfo
    {
        $models = $this->buildModelCatalog();
        return $models[$model] ?? throw new ProviderException("Unknown model: {$model}", $this->name());
    }

    /**
     * @return list<ModelInfo>
     */
    public function listModels(): array
    {
        return array_values($this->buildModelCatalog());
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
        if (isset($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
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
            $entry = [
                'role'    => $msg->role->value,
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
        $choice = $raw['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = new ToolCall(
                id: $tc['id'],
                name: $tc['function']['name'],
                arguments: json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
            );
        }

        $usage = new Usage(
            $raw['usage']['prompt_tokens'] ?? 0,
            $raw['usage']['completion_tokens'] ?? 0,
        );

        $finishReason = match ($choice['finish_reason'] ?? 'stop') {
            'stop'           => FinishReason::Stop,
            'tool_calls'     => FinishReason::ToolCall,
            'length'         => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default          => FinishReason::Stop,
        };

        return new Response(
            content:      $message['content'] ?? '',
            finishReason: $finishReason,
            usage:        $usage,
            toolCalls:    empty($toolCalls) ? null : $toolCalls,
            model:        $raw['model'] ?? $this->model,
            provider:     $this->name(),
            latencyMs:    $latencyMs,
        );
    }

    /**
     * @return array<string, ModelInfo>
     */
    private function buildModelCatalog(): array
    {
        return [
            'gpt-4.1' => new ModelInfo(
                name: 'gpt-4.1',
                provider: 'openai',
                tier: ModelTier::Balanced,
                contextWindow: 1_000_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 2.00,
                outputPricePerMillion: 8.00,
                supportsVision: true,
            ),
            'gpt-4.1-mini' => new ModelInfo(
                name: 'gpt-4.1-mini',
                provider: 'openai',
                tier: ModelTier::Fast,
                contextWindow: 1_000_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 0.40,
                outputPricePerMillion: 1.60,
            ),
            'o3' => new ModelInfo(
                name: 'o3',
                provider: 'openai',
                tier: ModelTier::Power,
                contextWindow: 200_000,
                maxOutputTokens: 100_000,
                inputPricePerMillion: 10.00,
                outputPricePerMillion: 40.00,
                supportsVision: true,
            ),
        ];
    }
}
