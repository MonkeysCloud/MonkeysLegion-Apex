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

/**
 * Fixed-size chunking — splits text by character/token count with overlap.
 */
final class FixedSizeChunker implements ChunkingStrategy
{
    public function __construct(
        private readonly int $chunkSize = 1000,
        private readonly int $overlap = 200,
    ) {}

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $pos    = 0;

        $step   = max(1, $this->chunkSize - $this->overlap);

        while ($pos < $length) {
            $chunk = mb_substr($text, $pos, $this->chunkSize);
            if (mb_strlen(trim($chunk)) > 0) {
                $chunks[] = trim($chunk);
            }
            $pos += $step;
        }

        return $chunks;
    }
}
