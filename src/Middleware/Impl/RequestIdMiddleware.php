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
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Request ID middleware — attaches a unique request identifier for distributed tracing.
 *
 * Every AI request gets a unique ID that can be used for correlation across
 * services, log aggregation, and debugging.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $headerName = 'X-Request-Id',
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $requestId = $context->metadata['request_id']
            ?? bin2hex(random_bytes(16));

        $context->metadata['request_id'] = $requestId;
        $context->metadata['request_id_header'] = $this->headerName;

        $result = $next($context);

        return $result;
    }
}
