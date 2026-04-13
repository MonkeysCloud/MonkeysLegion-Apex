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

namespace MonkeysLegion\Apex\Provider\Google;

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
 * Google Gemini / Vertex AI provider.
 *
 * Supports both Google AI Studio (generativelanguage.googleapis.com)
 * and Vertex AI (us-central1-aiplatform.googleapis.com) endpoints.
 */
final class GoogleProvider extends AbstractProvider
{
    protected const string DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';

    private readonly string $apiVersion;
    private readonly bool $isVertex;

    public function __construct(
        string  $apiKey,
        string  $model = 'gemini-2.5-flash',
        ?string $baseUrl = null,
        float   $timeout = 30.0,
        string  $apiVersion = 'v1beta',
        ?string $project = null,
        ?string $location = null,
    ) {
        // Detect Vertex AI mode
        $this->isVertex = $project !== null && $location !== null;
        $this->apiVersion = $apiVersion;

        if ($this->isVertex && $baseUrl === null) {
            $baseUrl = "https://{$location}-aiplatform.googleapis.com";
        }

        parent::__construct($apiKey, $model, $baseUrl, $timeout);
    }

    public function name(): string
    {
        return $this->isVertex ? 'vertex' : 'google';
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $body    = $this->buildBody($messages, $options);
        $model   = $options['model'] ?? $this->model;
        $start   = hrtime(true);
        $raw     = $this->request('POST', $this->buildEndpoint($model, 'generateContent'), $body);
        $latency = (hrtime(true) - $start) / 1e6;

        return $this->parseGoogleResponse($raw, $latency, $model);
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return \Generator<StreamChunk>
     */
    public function streamChat(array $messages, array $options = []): \Generator
    {
        $body  = $this->buildBody($messages, $options);
        $model = $options['model'] ?? $this->model;

        foreach ($this->streamRequest('POST', $this->buildEndpoint($model, 'streamGenerateContent') . '?alt=sse', $body) as $line) {
            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $finish = $data['candidates'][0]['finishReason'] ?? null;

            if ($finish !== null) {
                yield new StreamChunk(
                    event:        StreamEvent::Done,
                    delta:        $text,
                    finishReason: $finish,
                    usage:        isset($data['usageMetadata']) ? new Usage(
                        $data['usageMetadata']['promptTokenCount'] ?? 0,
                        $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                    ) : null,
                );
            } else {
                yield new StreamChunk(
                    event: StreamEvent::TextDelta,
                    delta: $text,
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
        $model  = 'text-embedding-004';
        $body   = [
            'requests' => array_map(fn(string $input) => [
                'model'   => "models/{$model}",
                'content' => ['parts' => [['text' => $input]]],
            ], $inputs),
        ];

        $raw = $this->request('POST', "/{$this->apiVersion}/models/{$model}:batchEmbedContents", $body);

        $vectors = [];
        foreach ($raw['embeddings'] ?? [] as $i => $embedding) {
            $values = $embedding['values'] ?? [];
            $vectors[] = new EmbeddingVector(
                input:      $inputs[$i],
                vector:     $values,
                dimensions: count($values),
                model:      $model,
            );
        }

        return $vectors;
    }

    public function modelInfo(string $model): ModelInfo
    {
        $catalog = $this->buildModelCatalog();
        return $catalog[$model] ?? throw new ProviderException("Unknown model: {$model}", $this->name());
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
            'x-goog-api-key: ' . $this->apiKey,
        ];
    }

    /**
     * Build API endpoint path.
     */
    private function buildEndpoint(string $model, string $method): string
    {
        return "/{$this->apiVersion}/models/{$model}:{$method}";
    }

    /**
     * Implementation required by AbstractProvider — delegates to parseGoogleResponse.
     *
     * @param array<string, mixed> $raw
     */
    protected function mapResponse(array $raw): Response
    {
        return $this->parseGoogleResponse($raw, 0.0, $this->model);
    }

    /**
     * Implementation required by AbstractProvider.
     *
     * @param list<Message> $messages
     * @return list<array<string, mixed>>
     */
    protected function mapMessages(array $messages): array
    {
        $mapped = [];
        foreach ($messages as $msg) {
            if ($msg->role === Role::System) continue;
            $role = match ($msg->role) {
                Role::User      => 'user',
                Role::Assistant => 'model',
                Role::Tool      => 'function',
                default         => 'user',
            };
            $mapped[] = ['role' => $role, 'parts' => [['text' => $msg->content]]];
        }
        return $mapped;
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildBody(array $messages, array $options): array
    {
        $contents = [];
        $system   = null;

        foreach ($messages as $msg) {
            if ($msg->role === Role::System) {
                $system = $msg->content;
                continue;
            }

            $role = match ($msg->role) {
                Role::User      => 'user',
                Role::Assistant => 'model',
                Role::Tool      => 'function',
                default         => 'user',
            };

            $parts = [['text' => $msg->content]];

            if ($msg->toolCalls !== null) {
                $parts = array_map(fn(ToolCall $tc) => [
                    'functionCall' => [
                        'name' => $tc->name,
                        'args' => (object) $tc->arguments,
                    ],
                ], $msg->toolCalls);
            }

            if ($msg->role === Role::Tool) {
                $parts = [[
                    'functionResponse' => [
                        'name'     => $msg->toolCallId ?? 'tool',
                        'response' => ['result' => $msg->content],
                    ],
                ]];
            }

            $contents[] = ['role' => $role, 'parts' => $parts];
        }

        $body = ['contents' => $contents];

        if ($system !== null) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        $config = [];
        if (isset($options['temperature'])) {
            $config['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $config['maxOutputTokens'] = $options['max_tokens'];
        }
        if (!empty($config)) {
            $body['generationConfig'] = $config;
        }

        if (isset($options['tools'])) {
            $body['tools'] = [['functionDeclarations' => $options['tools']]];
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function parseGoogleResponse(array $raw, float $latencyMs, string $model): Response
    {
        $candidate = $raw['candidates'][0] ?? [];
        $parts     = $candidate['content']['parts'] ?? [];

        $content   = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id:        'tc_' . bin2hex(random_bytes(4)),
                    name:      $part['functionCall']['name'],
                    arguments: (array) ($part['functionCall']['args'] ?? []),
                );
            }
        }

        $finishReason = match ($candidate['finishReason'] ?? 'STOP') {
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY'     => FinishReason::ContentFilter,
            default      => empty($toolCalls) ? FinishReason::Stop : FinishReason::ToolCall,
        };

        $usage = new Usage(
            $raw['usageMetadata']['promptTokenCount'] ?? 0,
            $raw['usageMetadata']['candidatesTokenCount'] ?? 0,
        );

        return new Response(
            content:      $content,
            finishReason: $finishReason,
            usage:        $usage,
            toolCalls:    empty($toolCalls) ? null : $toolCalls,
            model:        $model,
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
            'gemini-2.5-pro' => new ModelInfo(
                name: 'gemini-2.5-pro',
                provider: 'google',
                tier: ModelTier::Power,
                contextWindow: 1_000_000,
                maxOutputTokens: 65_536,
                inputPricePerMillion: 1.25,
                outputPricePerMillion: 10.00,
                supportsVision: true,
            ),
            'gemini-2.5-flash' => new ModelInfo(
                name: 'gemini-2.5-flash',
                provider: 'google',
                tier: ModelTier::Fast,
                contextWindow: 1_000_000,
                maxOutputTokens: 65_536,
                inputPricePerMillion: 0.15,
                outputPricePerMillion: 0.60,
                supportsVision: true,
            ),
            'gemini-2.0-flash' => new ModelInfo(
                name: 'gemini-2.0-flash',
                provider: 'google',
                tier: ModelTier::Fast,
                contextWindow: 1_000_000,
                maxOutputTokens: 8_192,
                inputPricePerMillion: 0.10,
                outputPricePerMillion: 0.40,
                supportsVision: true,
            ),
        ];
    }
}
