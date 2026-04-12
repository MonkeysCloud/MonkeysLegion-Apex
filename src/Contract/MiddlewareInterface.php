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

namespace MonkeysLegion\Apex\Contract;

use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * AI middleware contract — onion model around LLM calls.
 */
interface MiddlewareInterface
{
    /**
     * Process an AI request through this middleware.
     *
     * @param MiddlewareContext $context The request context.
     * @param callable         $next    The next middleware in the chain.
     * @return mixed                    Response or Generator for streaming.
     */
    public function handle(MiddlewareContext $context, callable $next): mixed;
}
