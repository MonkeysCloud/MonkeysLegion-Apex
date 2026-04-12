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

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\DTO\StreamChunk;
use MonkeysLegion\Apex\Enum\StreamEvent;
use MonkeysLegion\Apex\Schema\Schema;

/**
 * ObjectStream — streams structured output from LLM responses.
 *
 * Buffers JSON text from a text stream, then parses and validates
 * the result into a typed Schema object.
 *
 * @template T of Schema
 */
final class ObjectStream
{
    private string $buffer = '';
    private bool $consumed = false;

    /**
     * @param class-string<T> $class
     */
    public function __construct(
        private readonly TextStream $stream,
        private readonly string     $class,
        private readonly AI         $ai,
    ) {}

    /**
     * Iterate over raw text chunks while buffering.
     *
     * @return \Generator<StreamChunk>
     */
    public function chunks(): \Generator
    {
        foreach ($this->stream as $chunk) {
            $this->buffer .= $chunk->delta;
            yield $chunk;
        }
        $this->consumed = true;
    }

    /**
     * Get the full buffered text.
     */
    public function text(): string
    {
        if (!$this->consumed) {
            foreach ($this->chunks() as $_) {
                // Consume all chunks
            }
        }
        return $this->buffer ?: $this->stream->text();
    }

    /**
     * Parse the buffered JSON into a typed Schema object.
     *
     * @return T
     */
    public function object(): Schema
    {
        $text = $this->text();
        return $this->ai->extract($this->class, $text);
    }
}
