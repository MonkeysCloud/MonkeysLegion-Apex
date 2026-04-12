<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Embedding\EmbeddingManager;
use MonkeysLegion\Apex\Embedding\InMemoryStore;

/**
 * Vector memory — retrieves relevant past messages via embedding similarity.
 */
final class VectorMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];

    private InMemoryStore $store;

    public function __construct(
        private readonly EmbeddingManager $embeddings,
        private readonly int             $topK = 5,
    ) {
        $this->store = new InMemoryStore();
    }

    public function add(Message $message): void
    {
        $this->messages[] = $message;

        $vectors = $this->embeddings->embed([$message->content]);
        if (!empty($vectors)) {
            $this->store->add($vectors[0], [
                'index'   => count($this->messages) - 1,
                'role'    => $message->role->value,
                'content' => $message->content,
            ]);
        }
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Retrieve most relevant messages to a query.
     *
     * @return list<Message>
     */
    public function recall(string $query): array
    {
        $queryVectors = $this->embeddings->embed([$query]);
        if (empty($queryVectors)) {
            return [];
        }

        $results  = $this->store->search($queryVectors[0], $this->topK);
        $messages = [];

        foreach ($results as ['metadata' => $meta]) {
            $messages[] = $this->messages[$meta['index']];
        }

        return $messages;
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->store    = new InMemoryStore();
    }
}
