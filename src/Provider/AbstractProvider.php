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

namespace MonkeysLegion\Apex\Provider;

use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Exception\TimeoutException;

/**
 * Base provider with shared cURL HTTP logic.
 *
 * Handles retries, exponential backoff, SSE streaming, and error mapping.
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected const string DEFAULT_BASE_URL = '';

    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;
    protected float $timeout = 30.0;
    protected int $maxRetries = 3;

    public function __construct(
        string $apiKey,
        string $model,
        ?string $baseUrl = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ) {
        $this->apiKey     = $apiKey;
        $this->model      = $model;
        $this->baseUrl    = $baseUrl ?? static::DEFAULT_BASE_URL;
        $this->timeout    = $timeout ?? $this->timeout;
        $this->maxRetries = $maxRetries ?? $this->maxRetries;
    }

    /**
     * Send HTTP request to provider API.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed> Decoded JSON response.
     * @throws ProviderException
     * @throws TimeoutException
     */
    protected function request(string $method, string $path, array $body = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            $ch = curl_init($url);
            if ($ch === false) {
                throw new ProviderException('Failed to initialize cURL', $this->name());
            }

            try {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => (int) $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER     => $this->buildHeaders(),
                    CURLOPT_CUSTOMREQUEST  => strtoupper($method),
                ]);

                if (!empty($body)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                }

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);

                if ($response === false) {
                    if (str_contains($error, 'timed out')) {
                        throw new TimeoutException($this->timeout);
                    }
                    $lastError = new ProviderException(
                        "cURL error: {$error}",
                        $this->name(),
                        0,
                    );
                    $this->backoff($attempt);
                    continue;
                }

                if ($httpCode >= 400) {
                    $decoded = json_decode((string) $response, true) ?? [];
                    $message = $decoded['error']['message']
                        ?? $decoded['error']
                        ?? "HTTP {$httpCode}";

                    // Retry on 429 and 5xx
                    if ($httpCode === 429 || $httpCode >= 500) {
                        $lastError = new ProviderException(
                            (string) $message,
                            $this->name(),
                            $httpCode,
                            ['response' => $decoded],
                        );
                        $this->backoff($attempt);
                        continue;
                    }

                    throw new ProviderException(
                        (string) $message,
                        $this->name(),
                        $httpCode,
                        ['response' => $decoded],
                    );
                }

                /** @var array<string, mixed> */
                return json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
            } finally {
                curl_close($ch);
            }
        }

        throw $lastError ?? new ProviderException('Max retries exceeded', $this->name());
    }

    /**
     * Send streaming HTTP request, yields raw SSE data lines.
     *
     * @param array<string, mixed> $body
     * @return \Generator<string>
     */
    protected function streamRequest(string $method, string $path, array $body = []): \Generator
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ProviderException('Failed to initialize cURL', $this->name());
        }

        $buffer = '';

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_WRITEFUNCTION  => function ($ch, string $data) use (&$buffer): int {
                $buffer .= $data;
                return strlen($data);
            },
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        // Start request and yield SSE lines as they arrive
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new ProviderException("Streaming failed: HTTP {$httpCode}", $this->name(), $httpCode);
        }

        // Parse SSE lines
        foreach (explode("\n", $buffer) as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            if (str_starts_with($line, 'data: ')) {
                yield substr($line, 6);
            }
        }
    }

    /**
     * Build HTTP headers for the request.
     *
     * @return list<string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    /**
     * Map provider-specific response to standard Response DTO.
     *
     * @param array<string, mixed> $raw
     */
    abstract protected function mapResponse(array $raw): Response;

    /**
     * Map standard messages to provider-specific format.
     *
     * @param list<\MonkeysLegion\Apex\DTO\Message> $messages
     * @return list<array<string, mixed>>
     */
    abstract protected function mapMessages(array $messages): array;

    /**
     * Exponential backoff with jitter.
     */
    private function backoff(int $attempt): void
    {
        $delay = min(2 ** ($attempt - 1), 8) + (mt_rand(0, 1000) / 1000);
        usleep((int) ($delay * 1_000_000));
    }
}
