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
use Psr\Log\LoggerInterface;

/**
 * Telemetry middleware — logs request/response metrics.
 */
final class TelemetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $start = hrtime(true);

        try {
            $result = $next($context);

            $elapsed = (hrtime(true) - $start) / 1e6;

            $logData = [
                'model'      => $context->model,
                'latency_ms' => round($elapsed, 2),
                'messages'   => count($context->messages),
            ];

            if ($result instanceof Response) {
                $logData['tokens_in']     = $result->usage->promptTokens;
                $logData['tokens_out']    = $result->usage->completionTokens;
                $logData['tokens_total']  = $result->usage->totalTokens;
                $logData['finish_reason'] = $result->finishReason->value;
                $logData['provider']      = $result->provider;
            }

            $this->logger->info('AI request completed', $logData);
            $context->metadata['telemetry'] = $logData;

            return $result;
        } catch (\Throwable $e) {
            $elapsed = (hrtime(true) - $start) / 1e6;

            $this->logger->error('AI request failed', [
                'model'      => $context->model,
                'latency_ms' => round($elapsed, 2),
                'error'      => $e->getMessage(),
                'exception'  => $e::class,
            ]);

            throw $e;
        }
    }
}
