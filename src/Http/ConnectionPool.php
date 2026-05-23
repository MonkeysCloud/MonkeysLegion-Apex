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

namespace MonkeysLegion\Apex\Http;

/**
 * ConnectionPool — reuses cURL multi handles for efficient connection pooling.
 *
 * Reduces TLS handshake overhead by maintaining persistent connections
 * to frequently-accessed provider endpoints.
 */
final class ConnectionPool
{
    /** @var \CurlMultiHandle|null */
    private ?\CurlMultiHandle $multiHandle = null;

    /** @var array<string, \CurlHandle> Pooled handles keyed by base URL */
    private array $handles = [];

    public function __construct(
        private readonly int $maxConnections = 10,
    ) {}

    /**
     * Get or create a cURL handle for the given URL.
     * The handle has keep-alive and connection reuse enabled.
     */
    public function acquire(string $url): \CurlHandle
    {
        $baseUrl = $this->extractBaseUrl($url);

        if (isset($this->handles[$baseUrl])) {
            $handle = $this->handles[$baseUrl];
            curl_reset($handle);
            return $handle;
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new \RuntimeException('Failed to create cURL handle');
        }

        curl_setopt_array($handle, [
            CURLOPT_FORBID_REUSE  => false,
            CURLOPT_TCP_KEEPALIVE => 1,
        ]);

        // Evict oldest if at capacity
        if (count($this->handles) >= $this->maxConnections) {
            $key = array_key_first($this->handles);
            if ($key !== null) {
                unset($this->handles[$key]);
            }
        }

        $this->handles[$baseUrl] = $handle;
        return $handle;
    }

    /**
     * Release a handle back to the pool (no-op — handles are reused directly).
     */
    public function release(string $url): void
    {
        // Connection stays in the pool for reuse
    }

    /**
     * Close all pooled connections.
     */
    public function close(): void
    {
        $this->handles = [];

        if ($this->multiHandle !== null) {
            curl_multi_close($this->multiHandle);
            $this->multiHandle = null;
        }
    }

    /**
     * Get the number of pooled connections.
     */
    public function count(): int
    {
        return count($this->handles);
    }

    /**
     * Extract base URL (scheme + host + port) from a full URL.
     */
    private function extractBaseUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return "{$scheme}://{$host}{$port}";
    }

    public function __destruct()
    {
        $this->close();
    }
}
