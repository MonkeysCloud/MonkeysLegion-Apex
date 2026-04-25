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
 * Recursive text chunking — splits by structure (headers, paragraphs, sentences).
 *
 * Tries to split on natural boundaries, falling back to smaller boundaries
 * when chunks exceed the max size.
 */
final class RecursiveChunker implements ChunkingStrategy
{
    /** @var list<string> Separator hierarchy (most to least preferred) */
    private const array SEPARATORS = [
        "\n\n",  // Paragraphs
        "\n",    // Lines
        ". ",    // Sentences
        "! ",    // Exclamations
        "? ",    // Questions
        "; ",    // Semicolons
        ", ",    // Commas
        " ",     // Words
    ];

    public function __construct(
        private readonly int $maxChunkSize = 1000,
        private readonly int $overlap = 200,
    ) {}

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        return $this->splitRecursive($text, 0);
    }

    /**
     * @return list<string>
     */
    private function splitRecursive(string $text, int $separatorIndex): array
    {
        if (mb_strlen($text) <= $this->maxChunkSize) {
            $trimmed = trim($text);
            return $trimmed !== '' ? [$trimmed] : [];
        }

        if ($separatorIndex >= count(self::SEPARATORS)) {
            // Hard split as last resort
            $chunks = [];
            $pos = 0;
            while ($pos < mb_strlen($text)) {
                $chunk = mb_substr($text, $pos, $this->maxChunkSize);
                $trimmed = trim($chunk);
                if ($trimmed !== '') {
                    $chunks[] = $trimmed;
                }
                $pos += $this->maxChunkSize - $this->overlap;
            }
            return $chunks;
        }

        $separator = self::SEPARATORS[$separatorIndex];
        $parts = explode($separator, $text);

        if (count($parts) <= 1) {
            // Separator not found, try next one
            return $this->splitRecursive($text, $separatorIndex + 1);
        }

        $chunks  = [];
        $current = '';

        foreach ($parts as $part) {
            $candidate = $current !== '' ? $current . $separator . $part : $part;

            if (mb_strlen($candidate) > $this->maxChunkSize && $current !== '') {
                // Current chunk is full, emit it
                $chunks[] = trim($current);
                $current = $part;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            // Check if remainder is too large
            if (mb_strlen($current) > $this->maxChunkSize) {
                $chunks = array_merge($chunks, $this->splitRecursive($current, $separatorIndex + 1));
            } else {
                $chunks[] = trim($current);
            }
        }

        return $chunks;
    }
}
