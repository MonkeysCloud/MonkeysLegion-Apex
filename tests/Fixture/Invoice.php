<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests\Fixture;

use MonkeysLegion\Apex\Schema\Attribute\ArrayOf;
use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Optional;
use MonkeysLegion\Apex\Schema\Schema;

final class Invoice extends Schema
{
    public function __construct(
        #[Description('Vendor company name')]
        public readonly string $vendor,

        #[Description('Line items on the invoice')]
        #[ArrayOf(LineItem::class)]
        public readonly array $items,

        #[Description('Total amount in USD')]
        #[Constrain(min: 0)]
        public readonly float $total,

        #[Description('Optional purchase order number')]
        #[Optional]
        public readonly ?string $poNumber = null,
    ) {}
}
