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
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;
use Psr\SimpleCache\CacheInterface;

/**
 * Semantic cache middleware — caches LLM responses by content hash.
 */
final class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int            $ttl = 3600,
        private readonly string         $prefix = 'apex_cache:',
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $key = $this->buildKey($context);

        $cached = $this->cache->get($key);
        if ($cached instanceof Response) {
            $context->metadata['cache_hit'] = true;
            return $cached;
        }

        $result = $next($context);

        if ($result instanceof Response) {
            $this->cache->set($key, $result, $this->ttl);
            $context->metadata['cache_hit'] = false;
        }

        return $result;
    }

    private function buildKey(MiddlewareContext $context): string
    {
        $content = implode('|', array_map(
            fn($m) => $m->role->value . ':' . $m->content,
            $context->messages,
        ));
        $optionsHash = !empty($context->options) ? json_encode($context->options) : '';
        return $this->prefix . hash('xxh128', $content . $context->model . $optionsHash);
    }
}
