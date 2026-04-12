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

namespace MonkeysLegion\Apex\DTO;

/**
 * Embedding vector result.
 */
final readonly class EmbeddingVector
{
    /**
     * @param list<float> $vector
     */
    public function __construct(
        public string $input,
        public array  $vector,
        public int    $dimensions,
        public string $model = '',
    ) {}

    /**
     * Cosine similarity with another vector.
     */
    public function cosineSimilarity(self $other): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($this->vector), count($other->vector));

        for ($i = 0; $i < $len; $i++) {
            $dot   += $this->vector[$i] * $other->vector[$i];
            $normA += $this->vector[$i] ** 2;
            $normB += $other->vector[$i] ** 2;
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
