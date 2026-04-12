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
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Exception\BudgetExceededException;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Enforces cost budget limits before each request.
 */
final class CostBudgetMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CostTracker $tracker,
        private readonly float       $maxBudget,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        $current = $this->tracker->totalCost();

        if ($current >= $this->maxBudget) {
            throw new BudgetExceededException(
                budget: $this->maxBudget,
                spent:  $current,
            );
        }

        $result = $next($context);

        // Track cost from response
        if ($result instanceof Response) {
            $this->tracker->record(
                $context->model,
                $result->usage,
            );

            $newTotal = $this->tracker->totalCost();
            $context->metadata['cost_total']     = $newTotal;
            $context->metadata['cost_remaining'] = $this->maxBudget - $newTotal;
        }

        return $result;
    }
}
