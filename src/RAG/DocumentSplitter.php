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
 * Document splitter — splits documents into chunks for embedding.
 *
 * Supports different chunking strategies and metadata injection.
 */
final class DocumentSplitter
{
    public function __construct(
        private readonly ChunkingStrategy $strategy = new RecursiveChunker(),
    ) {}

    /**
     * Split a document into chunks with metadata.
     *
     * @param array<string, mixed> $metadata Base metadata to attach to each chunk
     * @return list<array{text: string, metadata: array<string, mixed>}>
     */
    public function split(string $document, array $metadata = []): array
    {
        $chunks  = $this->strategy->chunk($document);
        $results = [];

        foreach ($chunks as $index => $chunk) {
            $results[] = [
                'text'     => $chunk,
                'metadata' => array_merge($metadata, [
                    'chunk_index' => $index,
                    'chunk_total' => count($chunks),
                    'char_count'  => mb_strlen($chunk),
                ]),
            ];
        }

        return $results;
    }
}
