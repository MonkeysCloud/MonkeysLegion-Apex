<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Embedding;

use MonkeysLegion\Apex\DTO\EmbeddingVector;

/**
 * In-memory vector store for fast similarity search.
 */
final class InMemoryStore
{
    /** @var list<array{vector: EmbeddingVector, metadata: array<string, mixed>}> */
    private array $vectors = [];

    /**
     * Add a vector with metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function add(EmbeddingVector $vector, array $metadata = []): void
    {
        $this->vectors[] = ['vector' => $vector, 'metadata' => $metadata];
    }

    /**
     * Search for top-K similar vectors.
     *
     * @return list<array{vector: EmbeddingVector, metadata: array<string, mixed>, score: float}>
     */
    public function search(EmbeddingVector $query, int $topK = 5): array
    {
        $scored = [];

        foreach ($this->vectors as $entry) {
            $score = $query->cosineSimilarity($entry['vector']);
            $scored[] = [
                'vector'   => $entry['vector'],
                'metadata' => $entry['metadata'],
                'score'    => $score,
            ];
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Get total stored vectors.
     */
    public function count(): int
    {
        return count($this->vectors);
    }

    /**
     * Clear the store.
     */
    public function clear(): void
    {
        $this->vectors = [];
    }
}
