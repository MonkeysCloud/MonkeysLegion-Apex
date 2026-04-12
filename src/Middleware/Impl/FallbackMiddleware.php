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
use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Fallback middleware — tries alternate providers on failure.
 */
final class FallbackMiddleware implements MiddlewareInterface
{
    /** @var list<ProviderInterface> */
    private readonly array $fallbackProviders;

    public function __construct(ProviderInterface ...$providers)
    {
        $this->fallbackProviders = $providers;
    }

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        try {
            return $next($context);
        } catch (\Throwable $primaryError) {
            foreach ($this->fallbackProviders as $provider) {
                try {
                    $result = $provider->chat($context->messages, $context->options);
                    $context->metadata['fallback_provider'] = $provider->name();
                    $context->metadata['fallback_used']     = true;
                    return $result;
                } catch (\Throwable) {
                    continue;
                }
            }

            throw $primaryError;
        }
    }
}
