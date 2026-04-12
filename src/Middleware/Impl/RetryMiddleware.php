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

namespace MonkeysLegion\Apex\Middleware\Impl;

use MonkeysLegion\Apex\Contract\MiddlewareInterface;
use MonkeysLegion\Apex\Exception\ProviderException;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Retry middleware — retries failed requests with backoff.
 */
final class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int   $maxRetries = 3,
        private readonly float $baseDelay = 1.0,
        private readonly float $maxDelay = 8.0,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $result = $next($context);
                $context->metadata['retry_attempts'] = $attempt;
                return $result;
            } catch (ProviderException $e) {
                $lastError = $e;
                $attempt++;

                if ($attempt > $this->maxRetries) {
                    break;
                }

                // Only retry on retryable errors (429 rate limit, 5xx server errors, or unknown/0)
                if ($e->httpStatus !== 0 && $e->httpStatus !== 429 && $e->httpStatus < 500) {
                    throw $e;
                }

                $delay = min($this->baseDelay * (2 ** ($attempt - 1)), $this->maxDelay);
                $delay += random_int(0, 1000) / 1000;
                usleep((int) ($delay * 1_000_000));
            }
        }

        throw $lastError;
    }
}
