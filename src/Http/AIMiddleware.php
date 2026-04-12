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

use MonkeysLegion\Apex\Contract\MiddlewareInterface;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * HTTP middleware that wraps AI calls with request-scoped context.
 */
final class AIMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $requestId,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $context->metadata['request_id'] = $this->requestId;
        $context->metadata['timestamp']  = (new \DateTimeImmutable())->format('c');

        return $next($context);
    }
}
