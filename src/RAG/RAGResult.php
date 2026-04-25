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

namespace MonkeysLegion\Apex\RAG;

use MonkeysLegion\Apex\DTO\Response;

/**
 * Result from a RAG query, including the generated response and retrieved context.
 */
final readonly class RAGResult
{
    /**
     * @param list<array{vector: \MonkeysLegion\Apex\DTO\EmbeddingVector, metadata: array<string, mixed>, score: float}> $context
     */
    public function __construct(
        public Response $response,
        public array    $context,
        public string   $query,
    ) {}

    /**
     * Get the generated content.
     */
    public function content(): string
    {
        return $this->response->content;
    }

    /**
     * Get the number of context chunks that were used.
     */
    public function contextCount(): int
    {
        return count($this->context);
    }

    /**
     * Get the highest similarity score from retrieved context.
     */
    public function bestScore(): float
    {
        if (empty($this->context)) {
            return 0.0;
        }
        return max(array_column($this->context, 'score'));
    }

    /**
     * Check if any context was found.
     */
    public function hasContext(): bool
    {
        return !empty($this->context);
    }
}
