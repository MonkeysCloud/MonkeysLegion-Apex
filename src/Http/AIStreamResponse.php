<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Http;

use MonkeysLegion\Apex\Streaming\TextStream;

/**
 * HTTP response wrapper for AI streaming via SSE.
 */
final class AIStreamResponse
{
    public function __construct(
        private readonly TextStream $stream,
        private readonly string     $contentType = 'text/event-stream',
    ) {}

    /**
     * Send HTTP headers for SSE.
     */
    public function sendHeaders(): void
    {
        // Sanitize content type to prevent header injection
        $safeContentType = preg_replace('/[\r\n]/', '', $this->contentType);
        header("Content-Type: {$safeContentType}");
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
    }

    /**
     * Send SSE and flush output.
     */
    public function send(): void
    {
        $this->sendHeaders();

        foreach ($this->stream->toSSE() as $event) {
            echo $event;
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    }

    /**
     * Get the underlying stream.
     */
    public function stream(): TextStream
    {
        return $this->stream;
    }
}
