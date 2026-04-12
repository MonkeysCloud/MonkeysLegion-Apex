<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests\Fixture;

use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Example;
use MonkeysLegion\Apex\Schema\Schema;

final class LineItem extends Schema
{
    public function __construct(
        #[Description('Product or service name')]
        public readonly string $description,

        #[Description('Quantity ordered')]
        #[Constrain(min: 1)]
        public readonly int $quantity,

        #[Description('Unit price in USD')]
        #[Constrain(min: 0)]
        #[Example(9.99, 29.99)]
        public readonly float $unitPrice,
    ) {}
}
