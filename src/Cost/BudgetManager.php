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

namespace MonkeysLegion\Apex\Cost;

use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Exception\BudgetExceededException;

/**
 * Manages per-user/per-tenant cost budgets.
 */
final class BudgetManager
{
    /** @var array<string, array{limit: float, spent: float}> */
    private array $budgets = [];

    private readonly PricingRegistry $pricing;

    public function __construct(?PricingRegistry $pricing = null)
    {
        $this->pricing = $pricing ?? new PricingRegistry();
    }

    /**
     * Set budget for a scope (e.g., user ID, tenant).
     */
    public function setBudget(string $scope, float $limit): void
    {
        $this->budgets[$scope] = [
            'limit' => $limit,
            'spent' => $this->budgets[$scope]['spent'] ?? 0.0,
        ];
    }

    /**
     * Check if request is within budget and record cost.
     *
     * @throws BudgetExceededException
     */
    public function charge(string $scope, string $model, Usage $usage): float
    {
        $pricing = $this->pricing->get($model);
        $cost    = ($usage->promptTokens / 1_000_000) * $pricing->inputPerMillion
                 + ($usage->completionTokens / 1_000_000) * $pricing->outputPerMillion;

        if (isset($this->budgets[$scope])) {
            $newTotal = $this->budgets[$scope]['spent'] + $cost;
            if ($newTotal > $this->budgets[$scope]['limit']) {
                throw new BudgetExceededException(
                    budget: $this->budgets[$scope]['limit'],
                    spent:  $newTotal,
                    message: "Budget exceeded for scope '{$scope}'",
                );
            }
            $this->budgets[$scope]['spent'] = $newTotal;
        }

        return $cost;
    }

    /**
     * Get remaining budget for a scope.
     */
    public function remaining(string $scope): ?float
    {
        if (!isset($this->budgets[$scope])) {
            return null;
        }
        return max(0.0, $this->budgets[$scope]['limit'] - $this->budgets[$scope]['spent']);
    }

    /**
     * Get spent amount for a scope.
     */
    public function spent(string $scope): float
    {
        return $this->budgets[$scope]['spent'] ?? 0.0;
    }

    /**
     * Reset spending for a scope.
     */
    public function reset(string $scope): void
    {
        if (isset($this->budgets[$scope])) {
            $this->budgets[$scope]['spent'] = 0.0;
        }
    }
}
