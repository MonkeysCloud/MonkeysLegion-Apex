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

use MonkeysLegion\Apex\DTO\Cost;
use MonkeysLegion\Apex\DTO\Usage;

/**
 * Cost tracking contract.
 */
interface CostTrackerInterface
{
    /**
     * Record an AI request cost.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(string $model, Usage $usage, array $metadata = []): Cost;

    /**
     * Get total cost for the current period.
     */
    public function totalCost(): float;
}
