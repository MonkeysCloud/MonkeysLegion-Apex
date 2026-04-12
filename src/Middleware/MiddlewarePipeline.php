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

namespace MonkeysLegion\Apex\Middleware;

use MonkeysLegion\Apex\Contract\MiddlewareInterface;

/**
 * Executes middleware in onion order around the AI call.
 */
final class MiddlewarePipeline
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    /**
     * Push middleware onto the stack.
     */
    public function push(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Execute middleware around a core handler.
     *
     * @param callable(MiddlewareContext): mixed $core
     */
    public function execute(MiddlewareContext $context, callable $core): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(callable $next, MiddlewareInterface $mw) => fn(MiddlewareContext $ctx) => $mw->handle($ctx, $next),
            $core,
        );

        return $pipeline($context);
    }

    /**
     * Get middleware count.
     */
    public function count(): int
    {
        return count($this->middleware);
    }
}
