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
 * Fired when an AI request fails.
 */
final readonly class RequestFailedEvent extends AIEvent
{
    public function __construct(
        public \Throwable $error,
        public string     $model,
    ) {
        parent::__construct();
    }

    public function name(): string { return 'ai.request.failed'; }
}
