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

namespace MonkeysLegion\Apex\Streaming;

use MonkeysLegion\Apex\DTO\StreamChunk;

/**
 * StreamBuffer — buffers stream chunks with a defined capacity.
 */
final class StreamBuffer
{
    /** @var list<StreamChunk> */
    private array $chunks = [];
    private string $text = '';

    public function __construct(
        private readonly int $maxChunks = 1000,
    ) {}

    /**
     * Append a chunk to the buffer.
     */
    public function append(StreamChunk $chunk): void
    {
        if (count($this->chunks) >= $this->maxChunks) {
            // Flush oldest
            array_shift($this->chunks);
        }

        $this->chunks[] = $chunk;
        $this->text .= $chunk->delta;
    }

    /**
     * Get all buffered chunks.
     *
     * @return list<StreamChunk>
     */
    public function chunks(): array
    {
        return $this->chunks;
    }

    /**
     * Get full buffered text.
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Total chunks in buffer.
     */
    public function count(): int
    {
        return count($this->chunks);
    }

    /**
     * Clear the buffer.
     */
    public function flush(): void
    {
        $this->chunks = [];
        $this->text   = '';
    }
}
