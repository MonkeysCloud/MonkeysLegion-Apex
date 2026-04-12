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

namespace MonkeysLegion\Apex\Contract;

use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\StreamChunk;

/**
 * LLM provider contract.
 */
interface ProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): Response;

    /**
     * Send a streaming chat completion request.
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return \Generator<StreamChunk>
     */
    public function streamChat(array $messages, array $options = []): \Generator;

    /**
     * Generate embeddings for given input texts.
     *
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array;

    /**
     * Get information about a specific model.
     */
    public function modelInfo(string $model): ModelInfo;

    /**
     * List all available models for this provider.
     *
     * @return list<ModelInfo>
     */
    public function listModels(): array;

    /**
     * Get the provider name identifier.
     */
    public function name(): string;
}
