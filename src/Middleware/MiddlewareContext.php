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

namespace MonkeysLegion\Apex\Middleware;

use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Response;

/**
 * Shared context across the AI middleware pipeline.
 */
final class MiddlewareContext
{
    /** @var list<Message> */
    public array $messages;
    public string $model;
    /** @var array<string, mixed> */
    public array $options;
    public ?Response $response = null;

    /** @var array<string, mixed> Mutable metadata bag for middleware */
    public array $metadata = [];

    public float $startedAt;

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function __construct(array $messages, string $model, array $options)
    {
        $this->messages  = $messages;
        $this->model     = $model;
        $this->options   = $options;
        $this->startedAt = hrtime(true) / 1e6;
    }

    /**
     * Get elapsed time in milliseconds.
     */
    public function elapsedMs(): float
    {
        return (hrtime(true) / 1e6) - $this->startedAt;
    }
}
