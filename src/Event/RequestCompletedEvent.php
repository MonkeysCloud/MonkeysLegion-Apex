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

use MonkeysLegion\Apex\DTO\Response;

/**
 * Fired after an AI request completes.
 */
final readonly class RequestCompletedEvent extends AIEvent
{
    public function __construct(
        public Response $response,
        public float    $latencyMs,
        public string   $model,
    ) {
        parent::__construct();
    }

    public function name(): string { return 'ai.request.completed'; }
}
