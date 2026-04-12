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

namespace MonkeysLegion\Apex\Testing;

use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Enum\FinishReason;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Exception\ProviderException;

/**
 * Fake provider for unit tests — no API calls.
 *
 * Usage:
 *   $fake = FakeProvider::create()
 *       ->respondWith('Hello!')
 *       ->respondWith('World!');
 *
 *   $response = $fake->chat([Message::user('hi')]);
 *   // $response->content === 'Hello!'
 */
final class FakeProvider implements ProviderInterface
{
    /** @var list<Response|\Throwable|string> */
    private array $responses = [];
    private int $callIndex = 0;

    /** @var list<array{messages: list<Message>, options: array<string, mixed>}> */
    private array $calls = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Queue a response (string content or full Response DTO).
     */
    public function respondWith(string|Response $response): self
    {
        if (is_string($response)) {
            $response = new Response(
                content:      $response,
                finishReason: FinishReason::Stop,
                usage:        new Usage(10, 5),
                model:        'fake-model',
                provider:     'fake',
            );
        }
        $this->responses[] = $response;
        return $this;
    }

    /**
     * Queue a failure.
     */
    public function failWith(\Throwable $exception): self
    {
        $this->responses[] = $exception;
        return $this;
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response
    {
        $this->calls[] = ['messages' => $messages, 'options' => $options];

        if ($this->callIndex >= count($this->responses)) {
            return new Response(
                content:      '',
                finishReason: FinishReason::Stop,
                usage:        new Usage(0, 0),
                model:        'fake-model',
                provider:     'fake',
            );
        }

        $response = $this->responses[$this->callIndex++];

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return \Generator<StreamChunk>
     */
    public function streamChat(array $messages, array $options = []): \Generator
    {
        $response = $this->chat($messages, $options);
        $words = explode(' ', $response->content);

        foreach ($words as $i => $word) {
            yield new StreamChunk(
                event: StreamEvent::TextDelta,
                delta: ($i > 0 ? ' ' : '') . $word,
            );
        }

        yield new StreamChunk(
            event: StreamEvent::Done,
            usage: $response->usage,
            finishReason: 'stop',
        );
    }

    /**
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array
    {
        return array_map(fn(string $input) => new EmbeddingVector(
            input:      $input,
            vector:     array_fill(0, 3, 0.1),
            dimensions: 3,
            model:      'fake-embedding',
        ), $inputs);
    }

    public function modelInfo(string $model): ModelInfo
    {
        return new ModelInfo(
            name: $model,
            provider: 'fake',
            tier: ModelTier::Fast,
            contextWindow: 128000,
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
        return [$this->modelInfo('fake-model')];
    }

    public function name(): string
    {
        return 'fake';
    }

    // ── Assertion Helpers ──────────────────────────────────

    public function calledTimes(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{messages: list<Message>, options: array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function lastCall(): ?array
    {
        return $this->calls[count($this->calls) - 1] ?? null;
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->callIndex = 0;
        $this->calls = [];
    }
}
