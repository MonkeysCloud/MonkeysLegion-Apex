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
 * Telemetry middleware — logs + traces + metrics for AI requests.
 *
 * Integrates with the MonkeysLegion Telemetry package when available for:
 * - Distributed tracing (spans per AI request)
 * - Metrics (counters, histograms for latency and tokens)
 * - Structured logging
 *
 * Falls back to PSR logger when Telemetry is not installed.
 */
final class TelemetryMiddleware implements MiddlewareInterface
{
    private readonly bool $hasTelemetry;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->hasTelemetry = class_exists(\MonkeysLegion\Telemetry\Telemetry::class);
    }

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $start = hrtime(true);

        $callback = function () use ($context, $next, $start) {
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

                    // Record metrics via Telemetry package when available
                    if ($this->hasTelemetry && \MonkeysLegion\Telemetry\Telemetry::isInitialized()) {
                        $labels = ['model' => $context->model, 'provider' => $result->provider];
                        \MonkeysLegion\Telemetry\Telemetry::counter('apex_requests_total', 1.0, $labels);
                        \MonkeysLegion\Telemetry\Telemetry::histogram('apex_latency_ms', $elapsed, $labels);
                        \MonkeysLegion\Telemetry\Telemetry::counter('apex_tokens_total', (float) $result->usage->totalTokens, $labels);
                    }
                }

                // Log via Telemetry or PSR fallback
                if ($this->hasTelemetry && \MonkeysLegion\Telemetry\Telemetry::isInitialized()) {
                    \MonkeysLegion\Telemetry\Telemetry::log()?->info('AI request completed', $logData);
                } else {
                    $this->logger?->info('AI request completed', $logData);
                }

                $context->metadata['telemetry'] = $logData;

                return $result;
            } catch (\Throwable $e) {
                $elapsed = (hrtime(true) - $start) / 1e6;

                $errorData = [
                    'model'      => $context->model,
                    'latency_ms' => round($elapsed, 2),
                    'error'      => $e->getMessage(),
                    'exception'  => $e::class,
                ];

                if ($this->hasTelemetry && \MonkeysLegion\Telemetry\Telemetry::isInitialized()) {
                    \MonkeysLegion\Telemetry\Telemetry::counter('apex_errors_total', 1.0, ['model' => $context->model]);
                    \MonkeysLegion\Telemetry\Telemetry::log()?->error('AI request failed', $errorData);
                } else {
                    $this->logger?->error('AI request failed', $errorData);
                }

                throw $e;
            }
        };

        // Wrap in a tracing span if Telemetry is available and initialized
        if ($this->hasTelemetry && \MonkeysLegion\Telemetry\Telemetry::isInitialized()) {
            return \MonkeysLegion\Telemetry\Telemetry::trace(
                "apex.chat:{$context->model}",
                $callback,
                \MonkeysLegion\Telemetry\Tracing\SpanKind::CLIENT,
                ['model' => $context->model, 'messages' => count($context->messages)],
            );
        }

        return $callback();
    }
}
