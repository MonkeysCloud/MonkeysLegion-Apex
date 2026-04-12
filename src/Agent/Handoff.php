<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Agent;

use MonkeysLegion\Apex\DTO\Message;

/** Context handoff between agents. */
final readonly class Handoff
{
    /**
     * @param list<Message> $context
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $summary,
        public array  $context = [],
    ) {}
}
