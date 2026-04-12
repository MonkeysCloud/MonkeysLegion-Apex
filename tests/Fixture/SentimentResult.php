<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests\Fixture;

use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Optional;
use MonkeysLegion\Apex\Schema\Schema;

final class SentimentResult extends Schema
{
    public function __construct(
        #[Description('The detected sentiment')]
        #[Constrain(enum: ['positive', 'negative', 'neutral'])]
        public readonly string $sentiment,

        #[Description('Confidence score from 0 to 1')]
        #[Constrain(min: 0.0, max: 1.0)]
        public readonly float $confidence,

        #[Description('Optional explanation')]
        #[Optional]
        public readonly ?string $explanation = null,
    ) {}
}
