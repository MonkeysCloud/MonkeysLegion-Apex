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

namespace MonkeysLegion\Apex\Provider\Ollama;

use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Provider\AbstractProvider;

/**
 * Ollama local model provider (no API key needed).
 */
final class OllamaProvider extends AbstractProvider
{
    protected const string DEFAULT_BASE_URL = 'http://localhost:11434';

    public function __construct(
        string $model = 'llama3',
        ?string $baseUrl = null,
        ?float $timeout = null,
    ) {
        parent::__construct('', $model, $baseUrl, $timeout);
    }

    public function name(): string
    {
        return 'ollama';
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $body = [
            'model'    => $options['model'] ?? $this->model,
            'messages' => $this->mapMessages($messages),
            'stream'   => false,
        ];

        if (isset($options['temperature'])) {
            $body['options']['temperature'] = $options['temperature'];
        }

        $start = hrtime(true);
        $raw = $this->request('POST', '/api/chat', $body);
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
        $body = [
            'model'    => $options['model'] ?? $this->model,
            'messages' => $this->mapMessages($messages),
            'stream'   => true,
        ];

        foreach ($this->streamRequest('POST', '/api/chat', $body) as $line) {
            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            $done = $data['done'] ?? false;
            yield new StreamChunk(
                event: $done ? StreamEvent::Done : StreamEvent::TextDelta,
                delta: $data['message']['content'] ?? '',
                finishReason: $done ? 'stop' : null,
            );
        }
    }

    /**
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array
    {
        $vectors = [];
        foreach ($inputs as $input) {
            $raw = $this->request('POST', '/api/embeddings', [
                'model'  => $this->model,
                'prompt' => $input,
            ]);
            $embedding = $raw['embedding'] ?? [];
            $vectors[] = new EmbeddingVector(
                input:      $input,
                vector:     $embedding,
                dimensions: count($embedding),
                model:      $this->model,
            );
        }
        return $vectors;
    }

    public function modelInfo(string $model): ModelInfo
    {
        return new ModelInfo(
            name: $model,
            provider: 'ollama',
            tier: ModelTier::Fast,
            contextWindow: 8192,
            maxOutputTokens: 4096,
            inputPricePerMillion: 0.0,
            outputPricePerMillion: 0.0,
        );
    }

    /**
     * @return list<ModelInfo>
     */
    public function listModels(): array
    {
        $raw = $this->request('GET', '/api/tags');
        $models = [];
        foreach ($raw['models'] ?? [] as $m) {
            $models[] = new ModelInfo(
                name: $m['name'],
                provider: 'ollama',
                tier: ModelTier::Fast,
                contextWindow: 8192,
                maxOutputTokens: 4096,
                inputPricePerMillion: 0.0,
                outputPricePerMillion: 0.0,
            );
        }
        return $models;
    }

    /**
     * @return list<string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    /**
     * @param list<Message> $messages
     * @return list<array<string, mixed>>
     */
    protected function mapMessages(array $messages): array
    {
        return array_map(fn(Message $m) => [
            'role'    => $m->role->value,
            'content' => $m->content,
        ], $messages);
    }

    /**
     * @param array<string, mixed> $raw
     */
    protected function mapResponse(array $raw, float $latencyMs = 0.0): Response
    {
        $usage = new Usage(
            $raw['prompt_eval_count'] ?? 0,
            $raw['eval_count'] ?? 0,
        );

        return new Response(
            content:      $raw['message']['content'] ?? '',
            finishReason: FinishReason::Stop,
            usage:        $usage,
            model:        $raw['model'] ?? $this->model,
            provider:     $this->name(),
            latencyMs:    $latencyMs,
        );
    }
}
