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

use MonkeysLegion\Apex\Contract\CostTrackerInterface;
use MonkeysLegion\Apex\DTO\Cost;
use MonkeysLegion\Apex\DTO\Usage;

/**
 * Tracks cost of AI requests in-memory.
 */
final class CostTracker implements CostTrackerInterface
{
    /** @var list<Cost> */
    private array $requests = [];

    public function __construct(
        private readonly PricingRegistry $pricing,
    ) {}

    /**
     * Record an AI request cost.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(string $model, Usage $usage, array $metadata = []): Cost
    {
        $pricing = $this->pricing->get($model);

        $cost = new Cost(
            inputCost:  ($usage->promptTokens / 1_000_000) * $pricing->inputPerMillion,
            outputCost: ($usage->completionTokens / 1_000_000) * $pricing->outputPerMillion,
            model:      $model,
            metadata:   $metadata,
        );

        $this->requests[] = $cost;
        return $cost;
    }

    /**
     * Get total cost across all tracked requests.
     */
    public function totalCost(): float
    {
        return array_sum(array_map(fn(Cost $c) => $c->total, $this->requests));
    }

    /**
     * Get all tracked costs.
     *
     * @return list<Cost>
     */
    public function all(): array
    {
        return $this->requests;
    }

    /**
     * Clear all tracked costs.
     */
    public function reset(): void
    {
        $this->requests = [];
    }
}
