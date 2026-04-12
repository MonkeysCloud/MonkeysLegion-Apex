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

namespace MonkeysLegion\Apex\Provider\Anthropic;

use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\ToolCall;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\Role;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Provider\AbstractProvider;

/**
 * Anthropic Claude API provider.
 */
final class AnthropicProvider extends AbstractProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const string API_VERSION = '2023-06-01';

    public function name(): string
    {
        return 'anthropic';
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $body = $this->buildBody($messages, $options);
        $start = hrtime(true);
        $raw = $this->request('POST', '/v1/messages', $body);
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

        foreach ($this->streamRequest('POST', '/v1/messages', $body) as $line) {
            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            yield match ($data['type'] ?? '') {
                'content_block_delta' => new StreamChunk(
                    event: StreamEvent::TextDelta,
                    delta: $data['delta']['text'] ?? '',
                ),
                'message_delta' => new StreamChunk(
                    event: StreamEvent::Done,
                    finishReason: $data['delta']['stop_reason'] ?? 'stop',
                    usage: isset($data['usage']) ? new Usage(
                        $data['usage']['input_tokens'] ?? 0,
                        $data['usage']['output_tokens'] ?? 0,
                    ) : null,
                ),
                default => new StreamChunk(event: StreamEvent::TextDelta),
            };
        }
    }

    /**
     * Anthropic does not natively provide embeddings.
     *
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array
    {
        throw new ProviderException(
            'Anthropic does not support embeddings — use OpenAI or a dedicated embedding provider',
            $this->name(),
        );
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
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . self::API_VERSION,
        ];
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildBody(array $messages, array $options): array
    {
        $mapped = $this->mapMessages($messages);
        $system = null;

        // Extract system message (Anthropic uses top-level system param)
        foreach ($messages as $msg) {
            if ($msg->role === Role::System) {
                $system = $msg->content;
                break;
            }
        }

        $body = [
            'model'      => $options['model'] ?? $this->model,
            'messages'   => $mapped,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($system !== null) {
            $body['system'] = $system;
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
            // Skip system — handled separately
            if ($msg->role === Role::System) {
                continue;
            }

            $entry = [
                'role'    => $msg->role === Role::Tool ? 'user' : $msg->role->value,
                'content' => $msg->content,
            ];

            if ($msg->toolCalls !== null) {
                $entry['content'] = array_map(fn(ToolCall $tc) => [
                    'type' => 'tool_use',
                    'id'   => $tc->id,
                    'name' => $tc->name,
                    'input' => $tc->arguments,
                ], $msg->toolCalls);
            }

            if ($msg->role === Role::Tool) {
                $entry = [
                    'role' => 'user',
                    'content' => [[
                        'type'       => 'tool_result',
                        'tool_use_id' => $msg->toolCallId,
                        'content'    => $msg->content,
                    ]],
                ];
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
        $content = '';
        $toolCalls = [];

        foreach ($raw['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
            if (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                );
            }
        }

        $usage = new Usage(
            $raw['usage']['input_tokens'] ?? 0,
            $raw['usage']['output_tokens'] ?? 0,
        );

        $finishReason = match ($raw['stop_reason'] ?? 'stop') {
            'end_turn'    => FinishReason::Stop,
            'tool_use'    => FinishReason::ToolCall,
            'max_tokens'  => FinishReason::Length,
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

    /**
     * @return array<string, ModelInfo>
     */
    private function buildModelCatalog(): array
    {
        return [
            'claude-opus-4' => new ModelInfo(
                name: 'claude-opus-4',
                provider: 'anthropic',
                tier: ModelTier::Power,
                contextWindow: 200_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 15.00,
                outputPricePerMillion: 75.00,
                supportsVision: true,
            ),
            'claude-sonnet-4' => new ModelInfo(
                name: 'claude-sonnet-4',
                provider: 'anthropic',
                tier: ModelTier::Balanced,
                contextWindow: 200_000,
                maxOutputTokens: 64_000,
                inputPricePerMillion: 3.00,
                outputPricePerMillion: 15.00,
                supportsVision: true,
            ),
            'claude-haiku-4' => new ModelInfo(
                name: 'claude-haiku-4',
                provider: 'anthropic',
                tier: ModelTier::Fast,
                contextWindow: 200_000,
                maxOutputTokens: 64_000,
                inputPricePerMillion: 0.80,
                outputPricePerMillion: 4.00,
                supportsVision: true,
            ),
        ];
    }
}
