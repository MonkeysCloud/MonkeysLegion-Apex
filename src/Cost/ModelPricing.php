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

/**
 * Model pricing per 1M tokens.
 */
final readonly class ModelPricing
{
    public function __construct(
        public float $inputPerMillion,
        public float $outputPerMillion,
    ) {}
}
