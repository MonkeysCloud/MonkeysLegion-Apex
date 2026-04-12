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

namespace MonkeysLegion\Apex\Enum;

/**
 * Smart model-routing strategies.
 */
enum RouterStrategy: string
{
    case CostOptimized = 'cost_optimized';
    case QualityFirst  = 'quality_first';
    case LatencyFirst  = 'latency_first';
    case RoundRobin    = 'round_robin';
}
