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

namespace MonkeysLegion\Apex\Event;

/**
 * Base AI event — all events extend this.
 */
abstract readonly class AIEvent
{
    public \DateTimeImmutable $timestamp;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
    }

    abstract public function name(): string;
}
