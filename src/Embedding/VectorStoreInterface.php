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

namespace MonkeysLegion\Apex\Embedding;

use MonkeysLegion\Apex\DTO\EmbeddingVector;

/**
 * Vector store contract for embedding storage and similarity search.
 */
interface VectorStoreInterface
{
    /**
     * Add a vector with metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function add(EmbeddingVector $vector, array $metadata = []): void;

    /**
     * Search for top-K similar vectors.
     *
     * @return list<array{vector: EmbeddingVector, metadata: array<string, mixed>, score: float}>
     */
    public function search(EmbeddingVector $query, int $topK = 5): array;

    /**
     * Remove a vector by its input text (identifier).
     */
    public function delete(string $input): bool;

    /**
     * Get total stored vectors.
     */
    public function count(): int;

    /**
     * Clear the store.
     */
    public function clear(): void;
}
