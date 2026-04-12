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

use MonkeysLegion\Apex\DTO\Cost;

/**
 * Aggregates costs by model, provider, and time period.
 */
final class CostAggregator
{
    /**
     * Group costs by model.
     *
     * @param list<Cost> $costs
     * @return array<string, array{count: int, total: float, avg: float}>
     */
    public function byModel(array $costs): array
    {
        $groups = [];
        foreach ($costs as $cost) {
            $model = $cost->model;
            if (!isset($groups[$model])) {
                $groups[$model] = ['count' => 0, 'total' => 0.0];
            }
            $groups[$model]['count']++;
            $groups[$model]['total'] += $cost->total;
        }

        foreach ($groups as $model => &$data) {
            $data['avg'] = $data['count'] > 0 ? $data['total'] / $data['count'] : 0.0;
        }

        return $groups;
    }

    /**
     * Cost per period (hourly, daily).
     *
     * @param list<Cost> $costs
     * @return array<string, float>
     */
    public function byPeriod(array $costs, string $format = 'Y-m-d H:00'): array
    {
        $periods = [];
        foreach ($costs as $cost) {
            $key = $cost->timestamp->format($format);
            $periods[$key] = ($periods[$key] ?? 0.0) + $cost->total;
        }
        return $periods;
    }

    /**
     * Total input vs output cost breakdown.
     *
     * @param list<Cost> $costs
     * @return array{input: float, output: float, total: float, count: int}
     */
    public function summary(array $costs): array
    {
        $input  = 0.0;
        $output = 0.0;

        foreach ($costs as $cost) {
            $input  += $cost->inputCost;
            $output += $cost->outputCost;
        }

        return [
            'input'  => $input,
            'output' => $output,
            'total'  => $input + $output,
            'count'  => count($costs),
        ];
    }
}
