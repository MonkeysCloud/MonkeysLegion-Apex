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

use MonkeysLegion\Apex\Contract\StreamInterface;
use MonkeysLegion\Apex\DTO\StreamChunk;

/**
 * Wraps a streaming Generator into an iterable with text aggregation.
 */
final class TextStream implements StreamInterface
{
    private string $buffer = '';
    private bool $consumed = false;

    /**
     * @param \Generator<StreamChunk> $source
     */
    public function __construct(
        private \Generator $source,
    ) {}

    /**
     * Iterate over text deltas.
     *
     * @return \Generator<StreamChunk>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->source as $chunk) {
            $this->buffer .= $chunk->delta;
            yield $chunk;
        }
        $this->consumed = true;
    }

    /**
     * Get full text after streaming completes.
     */
    public function text(): string
    {
        if (!$this->consumed) {
            foreach ($this as $_) {}
        }
        return $this->buffer;
    }

    /**
     * Convert to SSE format for HTTP responses.
     *
     * @return \Generator<string>
     */
    public function toSSE(): \Generator
    {
        foreach ($this as $chunk) {
            yield "data: " . json_encode([
                'type'  => $chunk->event->value,
                'delta' => $chunk->delta,
            ]) . "\n\n";
        }
        yield "data: [DONE]\n\n";
    }

    /**
     * Pipe each chunk to a callback.
     */
    public function pipe(callable $callback): string
    {
        foreach ($this as $chunk) {
            $callback($chunk);
        }
        return $this->buffer;
    }
}
