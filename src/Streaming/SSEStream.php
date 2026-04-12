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
use MonkeysLegion\Apex\Enum\StreamEvent;

/**
 * SSEStream — parses raw Server-Sent Events into StreamChunks.
 */
final class SSEStream
{
    /**
     * Parse SSE lines into StreamChunks.
     *
     * @param iterable<string> $lines Raw SSE lines from HTTP response.
     * @return \Generator<StreamChunk>
     */
    public static function parse(iterable $lines): \Generator
    {
        $eventType = null;
        $data      = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                // Empty line = event boundary
                if ($data !== '') {
                    yield self::toChunk($eventType, $data);
                    $data = '';
                    $eventType = null;
                }
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventType = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        // Flush remaining data
        if ($data !== '') {
            yield self::toChunk($eventType, $data);
        }
    }

    private static function toChunk(?string $eventType, string $data): StreamChunk
    {
        if ($data === '[DONE]') {
            return new StreamChunk(event: StreamEvent::Done);
        }

        $decoded = json_decode($data, true);

        return new StreamChunk(
            event: StreamEvent::TextDelta,
            delta: is_array($decoded) ? ($decoded['text'] ?? $decoded['delta'] ?? $data) : $data,
        );
    }
}
