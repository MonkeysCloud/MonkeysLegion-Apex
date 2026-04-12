<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests\Fixture;

use MonkeysLegion\Apex\Schema\Attribute\ArrayOf;
use MonkeysLegion\Apex\Schema\Schema;

final class TagList extends Schema
{
    public function __construct(
        #[ArrayOf('string')]
        public readonly array $tags,
    ) {}
}
