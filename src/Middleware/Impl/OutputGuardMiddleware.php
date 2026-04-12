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
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Validates LLM output through guardrails after the call.
 */
final class OutputGuardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Guard $guard,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $result = $next($context);

        if ($result instanceof Response && $result->content !== '') {
            $guardResult = $this->guard->validateOutput($result->content);
            $context->metadata['output_guard_passed'] = $guardResult->passed;

            if (!$guardResult->passed && $guardResult->text !== $result->content) {
                // Return sanitized response
                return new Response(
                    content:      $guardResult->text,
                    finishReason: $result->finishReason,
                    usage:        $result->usage,
                    toolCalls:    $result->toolCalls,
                    reasoning:    $result->reasoning,
                    model:        $result->model,
                    provider:     $result->provider,
                    latencyMs:    $result->latencyMs,
                    cost:         $result->cost,
                    metadata:     array_merge($result->metadata, ['output_redacted' => true]),
                );
            }
        }

        return $result;
    }
}
