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

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\Embedding\VectorStoreInterface;

/**
 * RAG Pipeline — Retrieval-Augmented Generation orchestrator.
 *
 * Orchestrates the full RAG workflow:
 *   1. Ingest: split document → embed chunks → store in vector DB
 *   2. Query: embed question → search similar → inject context → generate
 */
final class RAGPipeline
{
    public function __construct(
        private readonly AI                   $ai,
        private readonly VectorStoreInterface $store,
        private readonly DocumentSplitter     $splitter = new DocumentSplitter(),
        private readonly int                  $topK = 5,
        private readonly float                $similarityThreshold = 0.7,
    ) {}

    /**
     * Ingest a document into the vector store.
     *
     * @param array<string, mixed> $metadata
     * @return int Number of chunks stored
     */
    public function ingest(string $document, array $metadata = []): int
    {
        $chunks = $this->splitter->split($document, $metadata);

        // Batch embed all chunks
        $texts = array_map(fn($c) => $c['text'], $chunks);
        $embeddings = $this->ai->embed($texts);

        foreach ($embeddings as $i => $embedding) {
            $this->store->add($embedding, $chunks[$i]['metadata']);
        }

        return count($chunks);
    }

    /**
     * Query with RAG context injection.
     *
     * @param array<string, mixed> $options
     */
    public function query(
        string  $question,
        ?string $system = null,
        ?string $model = null,
        array   $options = [],
    ): RAGResult {
        // 1. Embed the question
        $queryEmbeddings = $this->ai->embed($question);
        $queryVector = $queryEmbeddings[0] ?? null;

        if ($queryVector === null) {
            // Fallback: generate without context
            $response = $this->ai->generate($question, system: $system, model: $model, options: $options);
            return new RAGResult(
                response: $response,
                context:  [],
                query:    $question,
            );
        }

        // 2. Search for relevant context
        $results = $this->store->search($queryVector, $this->topK);

        // Filter by similarity threshold
        $relevant = array_filter($results, fn($r) => $r['score'] >= $this->similarityThreshold);
        $relevant = array_values($relevant);

        // 3. Build augmented prompt
        $contextParts = [];
        foreach ($relevant as $match) {
            $contextParts[] = $match['vector']->input;
        }

        $augmentedPrompt = $question;
        if (!empty($contextParts)) {
            $context = implode("\n\n---\n\n", $contextParts);
            $augmentedPrompt = "Use the following context to answer the question.\n\n"
                . "Context:\n{$context}\n\n"
                . "Question: {$question}";
        }

        // 4. Generate with augmented context
        $response = $this->ai->generate(
            $augmentedPrompt,
            system: $system,
            model: $model,
            options: $options,
        );

        return new RAGResult(
            response: $response,
            context:  $relevant,
            query:    $question,
        );
    }
}
