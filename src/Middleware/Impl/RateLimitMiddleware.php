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
use MonkeysLegion\Apex\Exception\RateLimitException;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Rate limiting middleware — token bucket algorithm.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private int $tokens;
    private float $lastRefill;

    public function __construct(
        private readonly int   $maxRequests = 60,
        private readonly float $windowSeconds = 60.0,
    ) {
        $this->tokens     = $this->maxRequests;
        $this->lastRefill = microtime(true);
    }

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $this->refill();

        if ($this->tokens <= 0) {
            throw new RateLimitException(
                retryAfter: (int) ceil($this->windowSeconds),
                message: "Rate limit exceeded: {$this->maxRequests} requests per {$this->windowSeconds}s",
            );
        }

        $this->tokens--;
        $context->metadata['rate_limit_remaining'] = $this->tokens;

        return $next($context);
    }

    private function refill(): void
    {
        $now     = microtime(true);
        $elapsed = $now - $this->lastRefill;
        $refill  = (int) floor(($elapsed / $this->windowSeconds) * $this->maxRequests);

        if ($refill > 0) {
            $this->tokens     = min($this->maxRequests, $this->tokens + $refill);
            $this->lastRefill = $now;
        }
    }
}
